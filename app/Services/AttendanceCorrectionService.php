<?php

namespace App\Services;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Support\AttendanceClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Correction workflow: request, approve and reject.
 *
 * Corrections never touch the original GPS evidence or original server
 * timestamps. Approved values are stored in the corrected_* columns.
 */
class AttendanceCorrectionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function request(AttendanceRecord $record, User $actor, array $data): AttendanceCorrection
    {
        return DB::transaction(function () use ($record, $actor, $data) {
            $locked = AttendanceRecord::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->corrections()
                ->where('status', AttendanceCorrection::STATUS_PENDING)
                ->exists()) {
                throw ValidationException::withMessages([
                    'correction' => 'Masih ada pengajuan koreksi yang menunggu keputusan untuk absensi ini.',
                ]);
            }

            $checkIn = $this->toCarbon($data['requested_check_in_at'] ?? null);
            $checkOut = $this->toCarbon($data['requested_check_out_at'] ?? null);
            $status = $data['requested_status'] ?? null;

            if ($status !== null && ! in_array($status, AttendanceRecord::CORRECTABLE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'requested_status' => 'Status hasil koreksi hanya boleh salah satu dari: '
                        .implode(', ', AttendanceRecord::CORRECTABLE_STATUSES).'.',
                ]);
            }

            if ($checkIn === null && $checkOut === null && $status === null) {
                throw ValidationException::withMessages([
                    'correction' => 'Isi minimal satu perubahan: waktu check-in, waktu check-out, atau status.',
                ]);
            }

            $this->assertConsistent(
                $checkIn ?? $locked->effectiveCheckInAt(),
                $checkOut ?? $locked->effectiveCheckOutAt(),
                $this->targetStatus($locked, $status),
                $status === null && $checkIn === null && $checkOut === null
            );

            return $locked->corrections()->create([
                'requested_by' => $actor->id,
                'requested_by_role' => $actor->role,
                'reason' => $data['reason'],
                'requested_check_in_at' => $checkIn,
                'requested_check_out_at' => $checkOut,
                'requested_status' => $status,
                'status' => AttendanceCorrection::STATUS_PENDING,
            ]);
        });
    }

    public function approve(
        AttendanceCorrection $correction,
        User $reviewer,
        ?string $note = null
    ): AttendanceCorrection {
        return DB::transaction(function () use ($correction, $reviewer, $note) {
            $lockedCorrection = AttendanceCorrection::query()
                ->whereKey($correction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedCorrection->isPending()) {
                throw ValidationException::withMessages([
                    'correction' => 'Pengajuan koreksi ini sudah diputuskan sebelumnya.',
                ]);
            }

            $record = AttendanceRecord::query()
                ->whereKey($lockedCorrection->attendance_record_id)
                ->lockForUpdate()
                ->firstOrFail();

            $beforeCheckIn = $record->effectiveCheckInAt();
            $beforeCheckOut = $record->effectiveCheckOutAt();
            $beforeStatus = $record->effectiveStatus();

            $targetCheckIn = $lockedCorrection->requested_check_in_at ?? $beforeCheckIn;
            $targetCheckOut = $lockedCorrection->requested_check_out_at ?? $beforeCheckOut;
            $targetStatus = $lockedCorrection->requested_status ?? $this->targetStatus($record, null);

            $this->assertConsistent($targetCheckIn, $targetCheckOut, $targetStatus);

            $now = AttendanceClock::nowUtc();
            $recordUpdates = [
                'snapshot_locked_at' => $record->snapshot_locked_at ?? $now,
            ];

            if ($lockedCorrection->requested_check_in_at !== null) {
                $recordUpdates['corrected_check_in_at'] = $lockedCorrection->requested_check_in_at;
            }

            if ($lockedCorrection->requested_check_out_at !== null) {
                $recordUpdates['corrected_check_out_at'] = $lockedCorrection->requested_check_out_at;
            }

            if ($lockedCorrection->requested_status !== null) {
                $recordUpdates['corrected_status'] = $lockedCorrection->requested_status;
            }

            $record->forceFill($recordUpdates)->save();

            $lockedCorrection->forceFill([
                'status' => AttendanceCorrection::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $now,
                'review_note' => $note,
                'before_check_in_at' => $beforeCheckIn,
                'before_check_out_at' => $beforeCheckOut,
                'before_status' => $beforeStatus,
                'applied_check_in_at' => $targetCheckIn,
                'applied_check_out_at' => $targetCheckOut,
                'applied_status' => $targetStatus,
            ])->save();

            return $lockedCorrection->refresh();
        });
    }

    public function reject(
        AttendanceCorrection $correction,
        User $reviewer,
        ?string $note = null
    ): AttendanceCorrection {
        return DB::transaction(function () use ($correction, $reviewer, $note) {
            $lockedCorrection = AttendanceCorrection::query()
                ->whereKey($correction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedCorrection->isPending()) {
                throw ValidationException::withMessages([
                    'correction' => 'Pengajuan koreksi ini sudah diputuskan sebelumnya.',
                ]);
            }

            $lockedCorrection->forceFill([
                'status' => AttendanceCorrection::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => AttendanceClock::nowUtc(),
                'review_note' => $note,
            ])->save();

            return $lockedCorrection->refresh();
        });
    }

    /**
     * Validate the effective times against the target status.
     */
    private function assertConsistent(
        ?Carbon $checkIn,
        ?Carbon $checkOut,
        string $status,
        bool $skipIfDynamic = false
    ): void {
        if ($skipIfDynamic && ! in_array($status, AttendanceRecord::CORRECTABLE_STATUSES, true)) {
            return;
        }

        if ($checkIn !== null && $checkOut !== null && $checkOut->lessThan($checkIn)) {
            throw ValidationException::withMessages([
                'requested_check_out_at' => 'Waktu check-out tidak boleh lebih awal dari waktu check-in.',
            ]);
        }

        $valid = match ($status) {
            AttendanceRecord::STATUS_COMPLETED => $checkIn !== null && $checkOut !== null,
            AttendanceRecord::STATUS_IN_PROGRESS => $checkIn !== null && $checkOut === null,
            AttendanceRecord::STATUS_ABSENT, AttendanceRecord::STATUS_DAY_OFF => $checkIn === null && $checkOut === null,
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                'requested_status' => match ($status) {
                    AttendanceRecord::STATUS_COMPLETED => 'Status Selesai membutuhkan waktu check-in dan check-out.',
                    AttendanceRecord::STATUS_IN_PROGRESS => 'Status Sedang Bertugas membutuhkan check-in dan belum boleh memiliki check-out.',
                    AttendanceRecord::STATUS_ABSENT => 'Status Tidak Hadir tidak boleh memiliki waktu check-in atau check-out efektif.',
                    AttendanceRecord::STATUS_DAY_OFF => 'Status Libur tidak boleh memiliki aktivitas check-in atau check-out.',
                    default => 'Status hasil koreksi tidak valid atau belum ditentukan.',
                },
            ]);
        }
    }

    private function targetStatus(AttendanceRecord $record, ?string $requestedStatus): string
    {
        if ($requestedStatus !== null) {
            return $requestedStatus;
        }

        $current = $record->effectiveStatus();

        if (! in_array($current, AttendanceRecord::CORRECTABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'requested_status' => 'Sertakan status hasil koreksi karena status saat ini masih bersifat dinamis.',
            ]);
        }

        return $current;
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return AttendanceClock::wibToUtc($value);
        }

        return AttendanceClock::wibToUtc((string) $value);
    }
}
