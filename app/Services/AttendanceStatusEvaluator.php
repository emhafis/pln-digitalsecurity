<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Shift;
use App\Support\AttendanceClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Marks records absent once the check-in deadline has passed.
 *
 * Dynamic statuses (scheduled/pending) are never persisted by this service.
 */
class AttendanceStatusEvaluator
{
    public function markAbsent(?Carbon $now = null): int
    {
        $now ??= AttendanceClock::nowUtc();
        $marked = 0;

        AttendanceRecord::query()
            ->where('shift_type', Shift::TYPE_WORKING)
            ->where('status', AttendanceRecord::STATUS_SCHEDULED)
            ->whereNull('check_in_at')
            ->whereNull('corrected_check_in_at')
            ->whereNull('snapshot_locked_at')
            ->whereNotNull('scheduled_end_at')
            ->orderBy('id')
            ->chunkById(100, function ($records) use ($now, &$marked) {
                foreach ($records as $record) {
                    if (! $this->isPastDeadline($record, $now)) {
                        continue;
                    }

                    $marked += $this->applyAbsent((int) $record->id, $now);
                }
            });

        return $marked;
    }

    private function isPastDeadline(AttendanceRecord $record, Carbon $now): bool
    {
        return $record->isPastAbsenceDeadline($now);
    }

    private function applyAbsent(int $recordId, Carbon $now): int
    {
        return DB::transaction(function () use ($recordId, $now) {
            $record = AttendanceRecord::query()
                ->whereKey($recordId)
                ->lockForUpdate()
                ->first();

            if ($record === null
                || $record->status !== AttendanceRecord::STATUS_SCHEDULED
                || $record->shift_type !== Shift::TYPE_WORKING
                || $record->check_in_at !== null
                || $record->corrected_check_in_at !== null
                || $record->snapshot_locked_at !== null
                || ! $this->isPastDeadline($record, $now)) {
                return 0;
            }

            $record->forceFill([
                'status' => AttendanceRecord::STATUS_ABSENT,
                'absent_marked_at' => $now,
                'snapshot_locked_at' => $now,
            ])->save();

            return 1;
        });
    }
}
