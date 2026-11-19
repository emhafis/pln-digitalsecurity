<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\SchedulePeriodMember;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleGenerationService
{
    public const MODE_FILL = 'fill';

    public const MODE_REGENERATE = 'regenerate';

    /**
     * Build a preview for filling missing schedules or regenerating rotation schedules.
     *
     * @return array<string, mixed>
     */
    public function preview(SchedulePeriod $period, string $mode = self::MODE_FILL): array
    {
        $mode = $this->normalizeMode($mode);

        $period->loadMissing([
            'unit:id,unit_name',
            'securityPost:id,post_code,name',
            'securityTeam:id,code,name',
            'members.security:id,user_id,full_name,registration_number',
            'members.schedulePattern.details.shift',
        ]);

        $start = $period->start_date?->copy()->startOfDay();
        $end = $period->end_date?->copy()->startOfDay();

        $errors = $this->validatePeriodConfiguration($period, $start, $end);

        $members = $period->members
            ->sortBy(fn (SchedulePeriodMember $member) => $member->security?->full_name ?? '')
            ->values();

        $daysCount = $start !== null && $end !== null && $start->lte($end)
            ? (int) $start->diffInDays($end) + 1
            : 0;

        $memberSummaries = $members->map(fn (SchedulePeriodMember $member) => [
            'id' => $member->id,
            'security_id' => $member->security_id,
            'full_name' => $member->security?->full_name ?? '-',
            'registration_number' => $member->security?->registration_number,
            'schedule_pattern_id' => $member->schedule_pattern_id,
            'pattern_name' => $member->schedulePattern?->name,
            'cycle_length' => $member->schedulePattern?->cycle_length,
            'start_offset' => (int) $member->start_offset,
            'rotation_day' => (int) $member->start_offset + 1,
            'first_shift_name' => $member->schedulePattern?->details
                ?->firstWhere('day_order', (int) $member->start_offset + 1)
                ?->shift?->name,
            'days_count' => $daysCount,
        ])->all();

        if ($errors !== [] || $start === null || $end === null || $daysCount === 0) {
            return $this->emptyPreview($period, $mode, $daysCount, $memberSummaries, $errors);
        }

        $candidates = $this->buildCandidates($members, $start, $end);

        if ($candidates === []) {
            return $this->emptyPreview(
                $period,
                $mode,
                $daysCount,
                $memberSummaries,
                ['Periode tidak menghasilkan kandidat jadwal.']
            );
        }

        $securityIds = collect($candidates)->pluck('security_id')->unique()->values()->all();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $existingByKey = Schedule::query()
            ->with(['shift:id,name,code,start_time,end_time,end_day_offset'])
            ->whereIn('security_id', $securityIds)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->get()
            ->keyBy(fn (Schedule $schedule) => $this->scheduleKey(
                (int) $schedule->security_id,
                $schedule->date->toDateString()
            ));

        $periodSchedules = Schedule::query()
            ->with([
                'security:id,full_name',
                'shift:id,name,code,start_time,end_time,end_day_offset',
            ])
            ->where('schedule_period_id', $period->id)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->get();

        $candidateKeys = collect($candidates)
            ->map(fn (array $candidate) => $this->scheduleKey($candidate['security_id'], $candidate['date']))
            ->flip();

        $rows = [];
        $conflictMessages = [];

        foreach ($candidates as $candidate) {
            $key = $this->scheduleKey($candidate['security_id'], $candidate['date']);
            $existing = $existingByKey->get($key);

            if ($existing !== null && (int) $existing->schedule_period_id !== (int) $period->id) {
                $conflictMessages[] = sprintf(
                    '%s (%s): sudah memiliki jadwal pada periode lain.',
                    $candidate['full_name'],
                    $candidate['date_label']
                );

                $rows[] = $this->previewRow($candidate, $existing, 'conflict');

                continue;
            }

            if ($existing === null) {
                $rows[] = $this->previewRow($candidate, null, 'create');

                continue;
            }

            if ($existing->source === Schedule::SOURCE_ADJUSTMENT) {
                $rows[] = $this->previewRow($candidate, $existing, 'keep_manual');

                continue;
            }

            if ($mode === self::MODE_FILL) {
                $rows[] = $this->previewRow($candidate, $existing, 'skip');

                continue;
            }

            $isUnchanged = (int) $existing->shift_id === $candidate['shift_id']
                && (int) $existing->schedule_pattern_id === $candidate['schedule_pattern_id']
                && (int) $existing->pattern_day_order === $candidate['pattern_day_order']
                && (int) $existing->schedule_period_member_id === $candidate['schedule_period_member_id'];

            $rows[] = $this->previewRow(
                $candidate,
                $existing,
                $isUnchanged ? 'unchanged' : 'update'
            );
        }

        foreach ($periodSchedules as $schedule) {
            $key = $this->scheduleKey(
                (int) $schedule->security_id,
                $schedule->date->toDateString()
            );

            if ($candidateKeys->has($key)) {
                continue;
            }

            if ($schedule->source === Schedule::SOURCE_ADJUSTMENT) {
                $rows[] = $this->previewRowFromExisting($schedule, 'keep_manual');

                continue;
            }

            if ($mode === self::MODE_REGENERATE) {
                $rows[] = $this->previewRowFromExisting($schedule, 'delete');
            }
        }

        $errors = array_values(array_unique(array_merge($errors, $conflictMessages)));

        return [
            'mode' => $mode,
            'period' => $this->periodSummary($period, $daysCount),
            'summary' => $this->summary($rows, $daysCount, $members->count()),
            'members' => $memberSummaries,
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * Persist the previewed generation plan in a single transaction.
     *
     * @return array<string, int|string>
     */
    public function generate(SchedulePeriod $period, string $mode, User $actor): array
    {
        $mode = $this->normalizeMode($mode);

        try {
            return DB::transaction(function () use ($period, $mode, $actor) {
                // Lock the period row before reading or writing its schedules so
                // generator and manual adjustment use the same lock order
                // (period first, schedule rows afterwards).
                $lockedPeriod = SchedulePeriod::query()
                    ->whereKey($period->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $preview = $this->preview($lockedPeriod, $mode);

                if ($preview['errors'] !== []) {
                    throw ValidationException::withMessages([
                        'generation' => $preview['errors'],
                    ]);
                }

                $result = [
                    'mode' => $mode,
                    'created' => 0,
                    'updated' => 0,
                    'deleted' => 0,
                    'skipped' => 0,
                    'manual_preserved' => 0,
                    'unchanged' => 0,
                ];

                foreach ($preview['rows'] as $row) {
                    $action = $row['action'];

                    if ($action === 'create') {
                        Schedule::create([
                            'schedule_period_id' => $lockedPeriod->id,
                            'schedule_period_member_id' => $row['schedule_period_member_id'],
                            'security_id' => $row['security_id'],
                            'shift_id' => $row['shift_id'],
                            'schedule_pattern_id' => $row['schedule_pattern_id'],
                            'pattern_day_order' => $row['pattern_day_order'],
                            'date' => $row['date'],
                            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
                            'created_by' => $actor->id,
                        ]);

                        $result['created']++;

                        continue;
                    }

                    if ($action === 'update') {
                        $schedule = Schedule::query()
                            ->whereKey($row['existing_id'])
                            ->where('schedule_period_id', $lockedPeriod->id)
                            ->where('source', Schedule::SOURCE_SYSTEM_GENERATED)
                            ->first();

                        if ($schedule === null) {
                            continue;
                        }

                        $schedule->update([
                            'schedule_period_member_id' => $row['schedule_period_member_id'],
                            'security_id' => $row['security_id'],
                            'shift_id' => $row['shift_id'],
                            'schedule_pattern_id' => $row['schedule_pattern_id'],
                            'pattern_day_order' => $row['pattern_day_order'],
                            'date' => $row['date'],
                            'updated_by' => $actor->id,
                        ]);

                        $result['updated']++;

                        continue;
                    }

                    if ($action === 'delete') {
                        $result['deleted'] += Schedule::query()
                            ->whereKey($row['existing_id'])
                            ->where('schedule_period_id', $lockedPeriod->id)
                            ->where('source', Schedule::SOURCE_SYSTEM_GENERATED)
                            ->delete();

                        continue;
                    }

                    if ($action === 'skip') {
                        $result['skipped']++;

                        continue;
                    }

                    if ($action === 'manual' || $action === 'keep_manual') {
                        $result['manual_preserved']++;

                        continue;
                    }

                    if ($action === 'unchanged') {
                        $result['unchanged']++;
                    }
                }

                return $result;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'generation' => 'Sebagian jadwal gagal disimpan karena bentrok dengan jadwal lain yang baru dibuat. Muat ulang pratinjau lalu coba lagi.',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function validatePeriodConfiguration(
        SchedulePeriod $period,
        ?Carbon $start,
        ?Carbon $end
    ): array {
        $errors = [];

        if ($start === null || $end === null) {
            $errors[] = 'Periode belum memiliki tanggal mulai dan tanggal selesai yang valid.';
        } elseif ($start->gt($end)) {
            $errors[] = 'Tanggal selesai periode tidak boleh lebih awal dari tanggal mulai.';
        }

        if ($period->members->isEmpty()) {
            $errors[] = 'Periode belum memiliki snapshot anggota.';
        }

        foreach ($period->members as $member) {
            $name = $member->security?->full_name ?? 'Satpam #'.$member->security_id;

            if ($member->security === null) {
                $errors[] = "{$name}: data satpam tidak ditemukan.";
            }

            if ($member->schedule_pattern_id === null) {
                $errors[] = "{$name}: belum memiliki pola rotasi.";

                continue;
            }

            $pattern = $member->schedulePattern;

            if ($pattern === null) {
                $errors[] = "{$name}: pola rotasi tidak ditemukan.";

                continue;
            }

            if ((int) $pattern->unit_id !== (int) $period->unit_id) {
                $errors[] = "{$name}: pola rotasi harus berada pada unit yang sama dengan periode.";
            }

            if (! $pattern->is_active) {
                $errors[] = "{$name}: pola rotasi {$pattern->name} sedang nonaktif.";
            }

            $cycleLength = (int) $pattern->cycle_length;

            if ($cycleLength < 1) {
                $errors[] = "{$name}: jumlah hari siklus pola {$pattern->name} tidak valid.";

                continue;
            }

            if ((int) $member->start_offset < 0 || (int) $member->start_offset >= $cycleLength) {
                $errors[] = sprintf(
                    '%s: posisi awal rotasi harus berada di antara 0 dan %d.',
                    $name,
                    $cycleLength - 1
                );
            }

            $details = $pattern->details->keyBy(fn ($detail) => (int) $detail->day_order);

            if ($details->count() !== $cycleLength) {
                $errors[] = sprintf(
                    '%s: detail pola %s tidak lengkap (%d dari %d hari).',
                    $name,
                    $pattern->name,
                    $details->count(),
                    $cycleLength
                );
            }

            for ($day = 1; $day <= $cycleLength; $day++) {
                $detail = $details->get($day);

                if ($detail === null) {
                    $errors[] = "{$name}: detail pola {$pattern->name} hari ke-{$day} tidak ditemukan.";

                    continue;
                }

                $shift = $detail->shift;

                if ($shift === null) {
                    $errors[] = "{$name}: shift pada pola {$pattern->name} hari ke-{$day} tidak ditemukan.";

                    continue;
                }

                if (! $shift->is_active) {
                    $errors[] = "{$name}: shift {$shift->name} pada pola {$pattern->name} hari ke-{$day} sedang nonaktif.";
                }

                if ((int) $shift->unit_id !== (int) $period->unit_id) {
                    $errors[] = "{$name}: shift {$shift->name} pada pola {$pattern->name} hari ke-{$day} harus berada pada unit yang sama dengan periode.";
                }
            }
        }

        return $errors;
    }

    /**
     * @param  Collection<int, SchedulePeriodMember>  $members
     * @return array<int, array<string, mixed>>
     */
    private function buildCandidates(Collection $members, Carbon $start, Carbon $end): array
    {
        $candidates = [];

        foreach ($members as $member) {
            $pattern = $member->schedulePattern;

            if ($pattern === null || (int) $pattern->cycle_length < 1) {
                continue;
            }

            $details = $pattern->details->keyBy(fn ($detail) => (int) $detail->day_order);
            $cycleLength = (int) $pattern->cycle_length;
            $cursor = $start->copy();

            while ($cursor->lte($end)) {
                $daysDifference = (int) $start->diffInDays($cursor);
                $dayOrder = (($member->start_offset + $daysDifference) % $cycleLength) + 1;
                $detail = $details->get($dayOrder);
                $shift = $detail?->shift;

                if ($shift === null) {
                    $cursor->addDay();

                    continue;
                }

                $candidates[] = [
                    'date' => $cursor->toDateString(),
                    'date_label' => $cursor->format('d M Y'),
                    'weekday' => $cursor->format('D'),
                    'schedule_period_member_id' => (int) $member->id,
                    'security_id' => (int) $member->security_id,
                    'full_name' => $member->security?->full_name ?? '-',
                    'schedule_pattern_id' => (int) $pattern->id,
                    'pattern_name' => $pattern->name,
                    'cycle_length' => $cycleLength,
                    'start_offset' => (int) $member->start_offset,
                    'pattern_day_order' => $dayOrder,
                    'shift_id' => (int) $shift->id,
                    'shift_code' => $shift->code,
                    'shift_name' => $shift->name,
                    'shift_time' => $this->timeRange(
                        $shift->start_time,
                        $shift->end_time,
                        (int) $shift->end_day_offset
                    ),
                    'shift_color' => $shift->color,
                ];

                $cursor->addDay();
            }
        }

        usort($candidates, function (array $a, array $b) {
            return [$a['date'], $a['full_name']] <=> [$b['date'], $b['full_name']];
        });

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function previewRow(array $candidate, ?Schedule $existing, string $action): array
    {
        return [
            'action' => $action,
            'date' => $candidate['date'],
            'date_label' => $candidate['date_label'],
            'weekday' => $candidate['weekday'],
            'schedule_period_member_id' => $candidate['schedule_period_member_id'],
            'security_id' => $candidate['security_id'],
            'full_name' => $candidate['full_name'],
            'pattern_name' => $candidate['pattern_name'],
            'cycle_length' => $candidate['cycle_length'],
            'start_offset' => $candidate['start_offset'],
            'pattern_day_order' => $candidate['pattern_day_order'],
            'schedule_pattern_id' => $candidate['schedule_pattern_id'],
            'shift_id' => $candidate['shift_id'],
            'shift_code' => $candidate['shift_code'],
            'shift_name' => $candidate['shift_name'],
            'shift_time' => $candidate['shift_time'],
            'shift_color' => $candidate['shift_color'],
            'existing_id' => $existing?->id,
            'existing_source' => $existing?->source,
            'existing_shift_id' => $existing?->shift_id,
            'existing_shift_name' => $existing?->shift?->name,
            'existing_shift_time' => $existing?->shift !== null
                ? $this->timeRange(
                    $existing->shift->start_time,
                    $existing->shift->end_time,
                    (int) $existing->shift->end_day_offset
                )
                : null,
            'existing_pattern_day_order' => $existing?->pattern_day_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function previewRowFromExisting(Schedule $schedule, string $action): array
    {
        return [
            'action' => $action,
            'date' => $schedule->date->toDateString(),
            'date_label' => $schedule->date->format('d M Y'),
            'weekday' => $schedule->date->format('D'),
            'schedule_period_member_id' => (int) $schedule->schedule_period_member_id,
            'security_id' => (int) $schedule->security_id,
            'full_name' => $schedule->security?->full_name ?? '-',
            'pattern_name' => null,
            'cycle_length' => null,
            'start_offset' => null,
            'pattern_day_order' => null,
            'schedule_pattern_id' => null,
            'shift_id' => null,
            'shift_code' => null,
            'shift_name' => null,
            'shift_time' => null,
            'shift_color' => null,
            'existing_id' => $schedule->id,
            'existing_source' => $schedule->source,
            'existing_shift_id' => $schedule->shift_id,
            'existing_shift_name' => $schedule->shift?->name,
            'existing_shift_time' => $schedule->shift !== null
                ? $this->timeRange(
                    $schedule->shift->start_time,
                    $schedule->shift->end_time,
                    (int) $schedule->shift->end_day_offset
                )
                : null,
            'existing_pattern_day_order' => $schedule->pattern_day_order,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function summary(array $rows, int $daysCount, int $membersCount): array
    {
        $counts = [
            'create' => 0,
            'skip' => 0,
            'keep_manual' => 0,
            'unchanged' => 0,
            'update' => 0,
            'delete' => 0,
            'conflict' => 0,
        ];

        foreach ($rows as $row) {
            if (isset($counts[$row['action']])) {
                $counts[$row['action']]++;
            }
        }

        return [
            'members_count' => $membersCount,
            'days_count' => $daysCount,
            'total_candidates' => $membersCount * $daysCount,
            'create_count' => $counts['create'],
            'skip_count' => $counts['skip'],
            'manual_count' => $counts['keep_manual'],
            'unchanged_count' => $counts['unchanged'],
            'update_count' => $counts['update'],
            'delete_count' => $counts['delete'],
            'conflict_count' => $counts['conflict'],
            'total_changes' => $counts['create'] + $counts['update'] + $counts['delete'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $memberSummaries
     * @param  array<int, string>  $errors
     * @return array<string, mixed>
     */
    private function emptyPreview(
        SchedulePeriod $period,
        string $mode,
        int $daysCount,
        array $memberSummaries,
        array $errors
    ): array {
        return [
            'mode' => $mode,
            'period' => $this->periodSummary($period, $daysCount),
            'summary' => $this->summary([], $daysCount, count($memberSummaries)),
            'members' => $memberSummaries,
            'rows' => [],
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function periodSummary(SchedulePeriod $period, int $daysCount): array
    {
        return [
            'id' => $period->id,
            'name' => $period->name,
            'unit_name' => $period->unit?->unit_name ?? '-',
            'post_name' => $period->securityPost?->name ?? '-',
            'team_name' => $period->securityTeam?->name ?? '-',
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'start_date_label' => $period->start_date?->format('d M Y'),
            'end_date_label' => $period->end_date?->format('d M Y'),
            'days_count' => $daysCount,
            'schedules_count' => (int) $period->schedules()->count(),
        ];
    }

    private function scheduleKey(int $securityId, string $date): string
    {
        return $securityId.'|'.$date;
    }

    private function normalizeMode(string $mode): string
    {
        return $mode === self::MODE_REGENERATE ? self::MODE_REGENERATE : self::MODE_FILL;
    }

    private function timeRange(?string $start, ?string $end, int $endDayOffset): ?string
    {
        if ($start === null || $start === '' || $end === null || $end === '') {
            return null;
        }

        return substr($start, 0, 5).' - '.substr($end, 0, 5).($endDayOffset ? ' (+1 hari)' : '');
    }
}
