<?php

namespace App\Services;

use App\Models\AttendanceSetting;
use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\Shift;
use App\Support\AttendanceClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Evaluates whether attendance data is ready to be generated for a period.
 *
 * The checker never writes attendance records. It returns a structured report
 * used by the admin dashboard, the generator and the artisan commands.
 */
class AttendanceReadinessChecker
{
    /**
     * @param  array<int, int>|null  $unitIds  null means every unit (superadmin).
     * @return array<string, mixed>
     */
    public function check(?array $unitIds = null, ?int $periodId = null): array
    {
        $global = $this->checkGlobalSettings();

        $query = SchedulePeriod::query()
            ->with([
                'unit:id,unit_name',
                'securityPost:id,name,latitude,longitude,unit_id',
                'securityTeam:id,name,code,security_post_id,is_active',
            ])
            ->orderBy('start_date')
            ->orderBy('id');

        if ($periodId !== null) {
            $query->whereKey($periodId);
        }

        if ($unitIds !== null) {
            $query->whereIn('unit_id', $unitIds);
        }

        $periods = $query->get();

        $periodResults = [];
        $totalSchedules = 0;
        $readySchedules = 0;

        foreach ($periods as $period) {
            $result = $this->checkPeriod($period, $global['ready']);
            $periodResults[] = $result;
            $totalSchedules += $result['schedule_count'];
            $readySchedules += $result['ready_schedule_count'];
        }

        $blockingPeriods = collect($periodResults)
            ->filter(fn (array $period) => $period['schedule_count'] > 0 && ! $period['ready'])
            ->values()
            ->all();

        return [
            'ready' => $global['ready'] && $blockingPeriods === [],
            'global' => $global,
            'periods' => $periodResults,
            'summary' => [
                'period_count' => count($periodResults),
                'blocking_period_count' => count($blockingPeriods),
                'schedule_count' => $totalSchedules,
                'ready_schedule_count' => $readySchedules,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkGlobalSettings(): array
    {
        $settings = AttendanceSetting::current();

        if ($settings === null) {
            return [
                'ready' => false,
                'settings' => null,
                'issues' => [[
                    'field' => null,
                    'message' => 'Pengaturan Absensi belum tersedia. Superadmin perlu mengisi pengaturan terlebih dahulu.',
                ]],
            ];
        }

        $issues = $settings->validationIssues();

        return [
            'ready' => $issues === [],
            'settings' => $settings,
            'issues' => $issues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function checkPeriod(SchedulePeriod $period, ?bool $globalReady = null): array
    {
        $period->loadMissing([
            'unit:id,unit_name',
            'securityPost:id,name,latitude,longitude,unit_id',
            'securityTeam:id,name,code,security_post_id,is_active',
        ]);

        $globalReady ??= $this->checkGlobalSettings()['ready'];

        $schedules = Schedule::query()
            ->with([
                'security.user:id,unit_id,is_active,role',
                'shift:id,unit_id,name,code,type,start_time,end_time,end_day_offset,is_active',
                'schedulePeriodMember:id,schedule_period_id,security_id',
            ])
            ->where('schedule_period_id', $period->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $hasWorkingSchedules = $schedules->contains(function (Schedule $schedule) {
            return $schedule->shift?->type === Shift::TYPE_WORKING;
        });

        $issues = [];

        if ($schedules->isEmpty()) {
            $issues[] = [
                'type' => 'no_schedules',
                'schedule_id' => null,
                'schedule_period_id' => $period->id,
                'security_id' => null,
                'work_date' => null,
                'date_label' => null,
                'security_name' => null,
                'message' => "{$period->name}: periode belum memiliki jadwal.",
            ];
        }

        foreach ($schedules as $schedule) {
            foreach ($this->scheduleIssues($schedule, $period, $hasWorkingSchedules) as $issue) {
                $issues[] = $issue;
            }
        }

        $scheduleIdsWithIssues = collect($issues)
            ->pluck('schedule_id')
            ->filter()
            ->unique()
            ->all();

        $readyScheduleCount = $schedules
            ->reject(fn (Schedule $schedule) => in_array($schedule->id, $scheduleIdsWithIssues, true))
            ->count();

        return [
            'period_id' => $period->id,
            'name' => $period->name,
            'unit_id' => $period->unit_id,
            'unit_name' => $period->unit?->unit_name ?? '-',
            'post_id' => $period->security_post_id,
            'post_name' => $period->securityPost?->name ?? '-',
            'team_name' => $period->securityTeam?->name ?? '-',
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'schedule_count' => $schedules->count(),
            'ready_schedule_count' => $readyScheduleCount,
            'ready' => $globalReady && $issues === [],
            'blocked_by_global' => ! $globalReady,
            'issues' => $issues,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scheduleIssues(
        Schedule $schedule,
        SchedulePeriod $period,
        bool $hasWorkingSchedules
    ): array {
        $issues = [];

        $issue = function (string $type, string $message) use ($schedule, $period): array {
            return [
                'type' => $type,
                'schedule_id' => $schedule->id,
                'schedule_period_id' => $period->id,
                'security_id' => $schedule->security_id,
                'work_date' => $schedule->date?->toDateString(),
                'date_label' => $schedule->date?->format('d M Y'),
                'security_name' => $schedule->security?->full_name,
                'message' => $message,
            ];
        };

        $security = $schedule->security;
        $name = $security?->full_name ?? 'Satpam #'.$schedule->security_id;
        $dateLabel = $schedule->date?->format('d M Y') ?? '-';

        if ($security === null || $security->user === null) {
            $issues[] = $issue(
                'invalid_security_or_membership',
                "{$name} ({$dateLabel}): data akun Satpam tidak ditemukan."
            );
        } elseif (! $security->user->is_active) {
            $issues[] = $issue(
                'invalid_security_or_membership',
                "{$name} ({$dateLabel}): akun Satpam sedang tidak aktif."
            );
        } elseif ($security->user->unit_id === null) {
            $issues[] = $issue(
                'invalid_security_or_membership',
                "{$name} ({$dateLabel}): Satpam belum memiliki unit."
            );
        }

        $membership = $schedule->schedulePeriodMember;

        if ($membership === null
            || (int) $membership->schedule_period_id !== (int) $period->id
            || (int) $membership->security_id !== (int) $schedule->security_id) {
            $issues[] = $issue(
                'invalid_security_or_membership',
                "{$name} ({$dateLabel}): keanggotaan periode tidak valid."
            );
        }

        if ($period->unit === null) {
            $issues[] = $issue(
                'invalid_schedule_relation',
                "{$name} ({$dateLabel}): unit periode tidak ditemukan."
            );
        }

        if ($period->securityPost === null) {
            $issues[] = $issue(
                'invalid_schedule_relation',
                "{$name} ({$dateLabel}): Pos Jaga periode tidak ditemukan."
            );
        }

        if ($period->securityTeam === null
            || (int) $period->securityTeam->security_post_id !== (int) $period->security_post_id) {
            $issues[] = $issue(
                'invalid_schedule_relation',
                "{$name} ({$dateLabel}): regu tidak sesuai dengan Pos Jaga periode."
            );
        } elseif (! $period->securityTeam->is_active) {
            $issues[] = $issue(
                'invalid_schedule_relation',
                "{$name} ({$dateLabel}): regu periode sedang tidak aktif."
            );
        }

        if ($schedule->shift_id === null) {
            $issues[] = $issue(
                'missing_shift',
                "{$name} ({$dateLabel}): jadwal belum memiliki shift."
            );

            return $issues;
        }

        $shift = $schedule->shift;

        if ($shift === null) {
            $issues[] = $issue(
                'invalid_shift',
                "{$name} ({$dateLabel}): shift pada jadwal tidak ditemukan."
            );

            return $issues;
        }

        if (! in_array($shift->type, [Shift::TYPE_WORKING, Shift::TYPE_DAY_OFF], true)) {
            $issues[] = $issue(
                'invalid_shift',
                "{$name} ({$dateLabel}): jenis shift {$shift->name} tidak valid."
            );

            return $issues;
        }

        if ((int) $shift->unit_id !== (int) $period->unit_id) {
            $issues[] = $issue(
                'invalid_shift',
                "{$name} ({$dateLabel}): shift {$shift->name} bukan milik unit periode."
            );

            return $issues;
        }

        if ($shift->type === Shift::TYPE_WORKING) {
            $timeIssue = $this->workingTimeIssue($shift);

            if ($timeIssue !== null) {
                $issues[] = $issue(
                    'incomplete_working_time',
                    "{$name} ({$dateLabel}): {$timeIssue}"
                );
            }

            if ($hasWorkingSchedules && ! $this->postHasCoordinates($period)) {
                $issues[] = $issue(
                    'missing_post_coordinates',
                    "{$name} ({$dateLabel}): koordinat Pos Jaga ".($period->securityPost?->name ?? '-').' belum tersedia.'
                );
            }
        }

        return $issues;
    }

    private function workingTimeIssue(Shift $shift): ?string
    {
        if ($shift->start_time === null || $shift->start_time === ''
            || $shift->end_time === null || $shift->end_time === '') {
            return 'shift kerja belum memiliki jam mulai dan jam selesai.';
        }

        $offset = (int) $shift->end_day_offset;

        if (! in_array($offset, [0, 1], true)) {
            return 'penanda lintas hari shift tidak valid.';
        }

        if ($offset === 0 && $shift->end_time <= $shift->start_time) {
            return 'jam selesai harus lebih akhir dari jam mulai.';
        }

        if ($offset === 1 && $shift->end_time > $shift->start_time) {
            return 'jam selesai shift lintas hari tidak boleh lebih besar dari jam mulai.';
        }

        return null;
    }

    private function postHasCoordinates(SchedulePeriod $period): bool
    {
        $post = $period->securityPost;

        if ($post === null) {
            return false;
        }

        return $post->latitude !== null
            && $post->longitude !== null
            && is_numeric($post->latitude)
            && is_numeric($post->longitude)
            && (float) $post->latitude >= -90
            && (float) $post->latitude <= 90
            && (float) $post->longitude >= -180
            && (float) $post->longitude <= 180;
    }

    /**
     * Readiness issues grouped per schedule id.
     *
     * @param  array<int, array<string, mixed>>  $issues
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    public function issuesBySchedule(array $issues): Collection
    {
        return collect($issues)
            ->filter(fn (array $issue) => ! empty($issue['schedule_id']))
            ->groupBy('schedule_id');
    }

    /**
     * Today's operational date in WIB, used for dashboards.
     */
    public function today(): Carbon
    {
        return AttendanceClock::nowWib();
    }
}
