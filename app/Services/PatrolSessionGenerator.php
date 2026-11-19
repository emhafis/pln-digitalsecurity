<?php

namespace App\Services;

use App\Models\PatrolPoint;
use App\Models\PatrolSession;
use App\Models\PatrolSessionMember;
use App\Models\PatrolSessionPoint;
use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\Shift;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatrolSessionGenerator
{
    /**
     * @return array<string, int>
     */
    public function generate(): array
    {
        $summary = [
            'active_periods' => 0,
            'eligible_groups' => 0,
            'sessions_created' => 0,
            'groups_skipped' => 0,
            'sessions_existing' => 0,
            'anomalies' => 0,
            'errors' => 0,
        ];

        $periods = SchedulePeriod::query()
            ->where('status', SchedulePeriod::STATUS_ACTIVE)
            ->with(['securityTeam', 'securityPost', 'unit'])
            ->get();

        $summary['active_periods'] = $periods->count();

        foreach ($periods as $period) {
            $this->generateForPeriod($period, $summary);
        }

        Log::info('patrol.sessions.generate.completed', $summary);

        return $summary;
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function generateForPeriod(SchedulePeriod $period, array &$summary): void
    {
        try {
            $schedules = $period->schedules()
                ->with(['security', 'shift', 'schedulePeriod.securityTeam', 'schedulePeriod.securityPost', 'schedulePeriod.unit'])
                ->whereBetween('date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
                ->get();

            $groups = [];

            foreach ($schedules as $schedule) {
                $issue = $this->eligibilityIssue($schedule, $period);
                if ($issue !== null) {
                    $this->logAnomaly($schedule, $period, $issue);
                    $summary['anomalies']++;

                    continue;
                }

                $key = $schedule->date->toDateString().'|'.$schedule->shift_id;
                $groups[$key][] = $schedule;
            }

            $pointsByPost = [];

            foreach ($groups as $group) {
                $summary['eligible_groups']++;
                $result = $this->processGroup($period, $group, $pointsByPost);
                if ($result === 'sessions_created') {
                    $summary['sessions_created']++;
                } elseif ($result === 'groups_skipped') {
                    $summary['groups_skipped']++;
                } elseif ($result === 'sessions_existing') {
                    $summary['sessions_existing']++;
                } else {
                    $summary['errors']++;
                }
            }
        } catch (\Throwable $e) {
            $summary['errors']++;
            Log::error('patrol.sessions.generate.period_failed', [
                'schedule_period_id' => $period->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, Schedule>  $group
     * @param  array<int, Collection<int, PatrolPoint>>  $pointsByPost
     */
    private function processGroup(SchedulePeriod $period, array $group, array &$pointsByPost): string
    {
        $first = $group[0];
        $workDate = $first->date->toDateString();
        $shiftId = (int) $first->shift_id;
        $postId = (int) $period->security_post_id;

        if (! array_key_exists($postId, $pointsByPost)) {
            $pointsByPost[$postId] = PatrolPoint::query()
                ->where('security_post_id', $postId)
                ->orderBy('id')
                ->get();
        }

        $points = $pointsByPost[$postId];
        if ($points->isEmpty()) {
            Log::warning('patrol.sessions.generate.no_patrol_points', [
                'schedule_period_id' => $period->id,
                'security_post_id' => $postId,
                'work_date' => $workDate,
                'shift_id' => $shiftId,
            ]);

            return 'groups_skipped';
        }

        try {
            return DB::transaction(function () use ($period, $group, $workDate, $shiftId, $points) {
                $existing = PatrolSession::query()
                    ->where('schedule_period_id', $period->id)
                    ->where('work_date', $workDate)
                    ->where('shift_id', $shiftId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return 'sessions_existing';
                }

                $first = $group[0];
                $shift = $first->shift;
                $team = $period->securityTeam;
                $post = $period->securityPost;
                $unit = $period->unit;

                $session = PatrolSession::query()->create([
                    'schedule_period_id' => $period->id,
                    'security_team_id' => $period->security_team_id,
                    'security_post_id' => $period->security_post_id,
                    'unit_id' => $period->unit_id,
                    'shift_id' => $shiftId,
                    'work_date' => $workDate,
                    'team_name' => (string) $team->name,
                    'team_code' => (string) $team->code,
                    'post_name' => (string) $post->name,
                    'post_code' => $post->post_code,
                    'unit_name' => (string) $unit->unit_name,
                    'unit_code' => (string) $unit->unit_code,
                    'shift_name' => (string) $shift->name,
                    'shift_code' => (string) $shift->code,
                    'shift_type' => (string) $shift->type,
                    'shift_start_time' => $shift->start_time,
                    'shift_end_time' => $shift->end_time,
                    'shift_end_day_offset' => (int) $shift->end_day_offset,
                    'status' => PatrolSession::STATUS_PENDING,
                    'started_at' => null,
                    'ended_at' => null,
                ]);

                foreach ($group as $schedule) {
                    $security = $schedule->security;
                    PatrolSessionMember::query()->create([
                        'patrol_session_id' => $session->id,
                        'schedule_id' => $schedule->id,
                        'security_id' => $schedule->security_id,
                        'security_name' => (string) $security->full_name,
                        'security_registration_number' => $security->registration_number,
                    ]);
                }

                $sequence = 1;
                foreach ($points as $point) {
                    PatrolSessionPoint::query()->create([
                        'patrol_session_id' => $session->id,
                        'patrol_point_id' => $point->id,
                        'patrol_point_name' => (string) $point->name,
                        'patrol_point_qr_code' => (string) $point->qr_code,
                        'sequence' => $sequence++,
                    ]);
                }

                return 'sessions_created';
            });
        } catch (UniqueConstraintViolationException|QueryException $e) {
            if ($this->isDuplicateKey($e)) {
                return 'sessions_existing';
            }

            Log::error('patrol.sessions.generate.group_failed', [
                'schedule_period_id' => $period->id,
                'work_date' => $workDate,
                'shift_id' => $shiftId,
                'error' => $e->getMessage(),
            ]);

            return 'errors';
        } catch (\Throwable $e) {
            Log::error('patrol.sessions.generate.group_failed', [
                'schedule_period_id' => $period->id,
                'work_date' => $workDate,
                'shift_id' => $shiftId,
                'error' => $e->getMessage(),
            ]);

            return 'errors';
        }
    }

    private function eligibilityIssue($schedule, SchedulePeriod $period): ?string
    {
        if ($schedule->date === null) {
            return 'work_date missing';
        }

        $workDate = $schedule->date->toDateString();
        if ($workDate < $period->start_date->toDateString() || $workDate > $period->end_date->toDateString()) {
            return 'work_date outside period range';
        }

        if ($schedule->shift_id === null) {
            return 'shift_id is null';
        }

        $shift = $schedule->shift;
        if ($shift === null) {
            return 'shift not found';
        }

        if (! $shift->is_active) {
            return 'shift inactive';
        }

        if ($shift->type === Shift::TYPE_DAY_OFF) {
            return 'shift is day_off';
        }

        if ($shift->type !== Shift::TYPE_WORKING) {
            return 'shift type not working';
        }

        if (empty($shift->start_time) || empty($shift->end_time)) {
            return 'working shift missing start/end time';
        }

        $security = $schedule->security;
        if ($security === null) {
            return 'security not found';
        }

        $team = $period->securityTeam;
        $post = $period->securityPost;
        $unit = $period->unit;
        if ($team === null || $post === null || $unit === null) {
            return 'period relations missing';
        }

        if ((int) $team->security_post_id !== (int) $period->security_post_id) {
            return 'team-post inconsistent';
        }

        if ((int) $post->unit_id !== (int) $period->unit_id) {
            return 'post-unit inconsistent';
        }

        return null;
    }

    private function logAnomaly($schedule, SchedulePeriod $period, string $reason): void
    {
        Log::warning('patrol.sessions.generate.anomaly', [
            'schedule_period_id' => $period->id,
            'schedule_id' => $schedule->id,
            'work_date' => $schedule->date?->toDateString(),
            'shift_id' => $schedule->shift_id,
            'reason' => $reason,
        ]);
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'Integrity constraint violation: 1062');
    }
}
