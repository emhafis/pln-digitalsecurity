<?php

namespace App\Services;

use App\Models\AttendanceGenerationAnomaly;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\Shift;
use App\Models\User;
use App\Support\AttendanceClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates and synchronises attendance records from schedules.
 *
 * The generator is idempotent (one record per schedule) and never changes a
 * snapshot that has been locked by attendance activity, an approved correction
 * or an absent marking.
 */
class AttendanceGenerator
{
    public function __construct(
        private readonly AttendanceReadinessChecker $readiness
    ) {}

    /**
     * Run the generator for every ready period within scope.
     *
     * @param  array<int, int>|null  $unitIds
     * @return array<string, mixed>
     */
    public function generateAll(?User $actor = null, ?array $unitIds = null, ?int $periodId = null): array
    {
        $report = $this->readiness->check($unitIds, $periodId);

        $result = [
            'ready' => $report['global']['ready'],
            'blocked_by_global' => ! $report['global']['ready'],
            'global_issues' => $report['global']['issues'],
            'periods' => [],
            'created' => 0,
            'synced' => 0,
            'unchanged' => 0,
            'locked' => 0,
            'anomalies' => 0,
            'errors' => [],
        ];

        if (! $report['global']['ready']) {
            return $result;
        }

        foreach ($report['periods'] as $periodReport) {
            if ($periodReport['schedule_count'] === 0) {
                continue;
            }

            $period = SchedulePeriod::query()->find($periodReport['period_id']);

            if ($period === null) {
                continue;
            }

            $periodResult = $this->generatePeriod($period, $actor);
            $result['periods'][] = $periodResult;

            $result['created'] += $periodResult['created'];
            $result['synced'] += $periodResult['synced'];
            $result['unchanged'] += $periodResult['unchanged'];
            $result['locked'] += $periodResult['locked'];
            $result['anomalies'] += $periodResult['anomalies'];

            if (! $periodResult['ready']) {
                $result['errors'] = array_merge($result['errors'], $periodResult['errors']);
            }
        }

        $result['ready'] = $result['errors'] === [];

        return $result;
    }

    /**
     * Generate attendance records for a single period (all-or-nothing).
     *
     * @return array<string, mixed>
     */
    public function generatePeriod(SchedulePeriod $period, ?User $actor = null): array
    {
        return DB::transaction(function () use ($period, $actor) {
            $lockedPeriod = SchedulePeriod::query()
                ->whereKey($period->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $check = $this->readiness->checkPeriod($lockedPeriod);
            $now = AttendanceClock::nowUtc();

            if (! $check['ready']) {
                $anomalies = $this->syncAnomalies($lockedPeriod, $check['issues'], $actor, $now);

                return [
                    'period_id' => $lockedPeriod->id,
                    'name' => $lockedPeriod->name,
                    'ready' => false,
                    'created' => 0,
                    'synced' => 0,
                    'unchanged' => 0,
                    'locked' => 0,
                    'anomalies' => $anomalies,
                    'errors' => collect($check['issues'])
                        ->pluck('message')
                        ->unique()
                        ->values()
                        ->all(),
                ];
            }

            $settings = AttendanceSetting::current();

            if ($settings === null || ! $settings->isValid()) {
                return [
                    'period_id' => $lockedPeriod->id,
                    'name' => $lockedPeriod->name,
                    'ready' => false,
                    'created' => 0,
                    'synced' => 0,
                    'unchanged' => 0,
                    'locked' => 0,
                    'anomalies' => 0,
                    'errors' => ['Pengaturan Absensi belum tersedia atau tidak valid.'],
                ];
            }

            $resolved = $this->syncAnomalies($lockedPeriod, [], $actor, $now);

            $schedules = Schedule::query()
                ->with([
                    'security.user:id,unit_id,is_active',
                    'shift',
                ])
                ->where('schedule_period_id', $lockedPeriod->id)
                ->orderBy('date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $counts = [
                'created' => 0,
                'synced' => 0,
                'unchanged' => 0,
                'locked' => 0,
            ];

            foreach ($schedules as $schedule) {
                $action = $this->upsertRecord($schedule, $lockedPeriod, $settings, $now);
                $counts[$action]++;
            }

            return [
                'period_id' => $lockedPeriod->id,
                'name' => $lockedPeriod->name,
                'ready' => true,
                'created' => $counts['created'],
                'synced' => $counts['synced'],
                'unchanged' => $counts['unchanged'],
                'locked' => $counts['locked'],
                'anomalies' => $resolved,
                'errors' => [],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function upsertRecord(
        Schedule $schedule,
        SchedulePeriod $period,
        AttendanceSetting $settings,
        Carbon $now
    ): string {
        $snapshot = $this->buildSnapshot($schedule, $period, $settings);

        $existing = AttendanceRecord::query()
            ->where('schedule_id', $schedule->id)
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            if ($snapshot['shift_type'] === Shift::TYPE_DAY_OFF) {
                $snapshot['status'] = AttendanceRecord::STATUS_DAY_OFF;
                $snapshot['snapshot_locked_at'] = $now;
            } else {
                $snapshot['status'] = AttendanceRecord::STATUS_SCHEDULED;
            }

            $snapshot['last_synced_at'] = $now;

            AttendanceRecord::query()->create($snapshot);

            return 'created';
        }

        if (! $existing->canSyncSnapshot()) {
            return 'locked';
        }

        $existing->forceFill($snapshot);

        if ($snapshot['shift_type'] === Shift::TYPE_DAY_OFF) {
            $existing->forceFill([
                'status' => AttendanceRecord::STATUS_DAY_OFF,
                'snapshot_locked_at' => $existing->snapshot_locked_at ?? $now,
            ]);
        }

        if (! $existing->isDirty()) {
            return 'unchanged';
        }

        $existing->forceFill(['last_synced_at' => $now])->save();

        return 'synced';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(
        Schedule $schedule,
        SchedulePeriod $period,
        AttendanceSetting $settings
    ): array {
        $shift = $schedule->shift;
        $security = $schedule->security;
        $post = $period->securityPost;
        $team = $period->securityTeam;
        $unit = $period->unit;
        $workDate = $schedule->date->toDateString();

        $isWorking = $shift->type === Shift::TYPE_WORKING;

        $scheduledStart = $isWorking
            ? AttendanceClock::scheduledStart($workDate, (string) $shift->start_time)
            : null;

        $scheduledEnd = $isWorking
            ? AttendanceClock::scheduledEnd(
                $workDate,
                (string) $shift->end_time,
                (int) $shift->end_day_offset
            )
            : null;

        return [
            'schedule_id' => $schedule->id,
            'schedule_period_id' => $period->id,
            'security_id' => $schedule->security_id,
            'security_post_id' => $period->security_post_id,
            'shift_id' => $schedule->shift_id,
            'work_date' => $workDate,
            'security_name' => $security?->full_name ?? '-',
            'security_registration_number' => $security?->registration_number,
            'unit_id' => $period->unit_id,
            'unit_name' => $unit?->unit_name ?? '-',
            'security_team_id' => $period->security_team_id,
            'security_team_name' => $team?->name ?? '-',
            'security_post_name' => $post?->name ?? '-',
            'shift_name' => $shift->name,
            'shift_code' => $shift->code,
            'shift_type' => $shift->type,
            'shift_start_time' => $shift->start_time,
            'shift_end_time' => $shift->end_time,
            'shift_end_day_offset' => (int) $shift->end_day_offset,
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledEnd,
            'post_latitude' => $post?->latitude,
            'post_longitude' => $post?->longitude,
            'setting_gps_radius_meters' => $settings->gps_radius_meters,
            'setting_check_in_open_minutes' => $settings->check_in_open_minutes,
            'setting_late_tolerance_minutes' => $settings->late_tolerance_minutes,
            'setting_early_checkout_tolerance_minutes' => $settings->early_checkout_tolerance_minutes,
            'setting_checkout_close_minutes' => $settings->checkout_close_minutes,
            'setting_max_gps_accuracy_meters' => $settings->max_gps_accuracy_meters,
        ];
    }

    /**
     * Create/update active anomalies for the period and resolve those that no
     * longer apply. Returns the number of active anomalies touched.
     *
     * @param  array<int, array<string, mixed>>  $issues
     */
    private function syncAnomalies(
        SchedulePeriod $period,
        array $issues,
        ?User $actor,
        Carbon $now
    ): int {
        $activeKeys = [];
        $touched = 0;

        foreach ($issues as $issue) {
            if (empty($issue['schedule_id']) || empty($issue['type'])) {
                continue;
            }

            $key = $issue['schedule_id'].'|'.$issue['type'];
            $activeKeys[$key] = true;

            AttendanceGenerationAnomaly::query()->updateOrCreate(
                [
                    'schedule_id' => $issue['schedule_id'],
                    'anomaly_type' => $issue['type'],
                ],
                [
                    'schedule_period_id' => $issue['schedule_period_id'] ?? $period->id,
                    'security_id' => $issue['security_id'] ?? null,
                    'work_date' => $issue['work_date'] ?? null,
                    'details' => $issue['message'] ?? null,
                    'detected_at' => $now,
                    'resolved_at' => null,
                    'resolved_by' => null,
                    'resolution_note' => null,
                ]
            );

            $touched++;
        }

        $activeAnomalies = AttendanceGenerationAnomaly::query()
            ->where('schedule_period_id', $period->id)
            ->whereNull('resolved_at')
            ->get();

        foreach ($activeAnomalies as $anomaly) {
            $key = $anomaly->schedule_id.'|'.$anomaly->anomaly_type;

            if (isset($activeKeys[$key])) {
                continue;
            }

            $anomaly->forceFill([
                'resolved_at' => $now,
                'resolved_by' => $actor?->id,
                'resolution_note' => 'Data telah diperbaiki dan tidak lagi terdeteksi sebagai masalah.',
            ])->save();
        }

        return $touched;
    }
}
