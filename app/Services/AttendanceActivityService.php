<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Shift;
use App\Support\AttendanceClock;
use App\Support\GeoDistance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handles check-in / check-out with server-side geofence and time validation.
 */
class AttendanceActivityService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function checkIn(AttendanceRecord $record, array $payload, ?Carbon $now = null): AttendanceRecord
    {
        return DB::transaction(function () use ($record, $payload, $now) {
            $locked = AttendanceRecord::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $now ??= AttendanceClock::nowUtc();

            $this->assertWorking($locked);

            if ($locked->effectiveCheckInAt() !== null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Anda sudah melakukan check-in untuk jadwal ini.',
                ]);
            }

            $effective = $locked->effectiveStatus($now);

            if (in_array($effective, [
                AttendanceRecord::STATUS_ABSENT,
                AttendanceRecord::STATUS_COMPLETED,
                AttendanceRecord::STATUS_DAY_OFF,
            ], true)) {
                throw ValidationException::withMessages([
                    'attendance' => 'Check-in tidak dapat dilakukan karena status absensi sudah '
                        .AttendanceRecord::statusLabel($effective).'.',
                ]);
            }

            if ($locked->scheduled_start_at === null || $locked->scheduled_end_at === null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Jadwal shift belum memiliki waktu yang lengkap.',
                ]);
            }

            $opensAt = $locked->scheduled_start_at->copy()
                ->subMinutes((int) $locked->setting_check_in_open_minutes);

            if ($now->lessThan($opensAt)) {
                throw ValidationException::withMessages([
                    'attendance' => 'Check-in belum dibuka. Silakan coba lagi '
                        .AttendanceClock::utcToWib($opensAt)->format('H:i').' WIB.',
                ]);
            }

            $toleranceMinutes = $locked->checkInToleranceMinutes();
            $deadline = $locked->scheduled_start_at->copy()->addMinutes($toleranceMinutes);

            if ($now->greaterThan($deadline)) {
                throw ValidationException::withMessages([
                    'attendance' => 'Waktu absen masuk telah melewati batas toleransi. Silakan hubungi Admin.',
                ]);
            }

            $gps = $this->validateGps($locked, $payload);
            $lateMinutes = max(0, $locked->scheduled_start_at->diffInMinutes($now, false));

            $locked->forceFill([
                'check_in_at' => $now,
                'check_in_captured_at' => $this->parseCapturedAt($payload['captured_at'] ?? null),
                'check_in_latitude' => $gps['latitude'],
                'check_in_longitude' => $gps['longitude'],
                'check_in_accuracy_meters' => $gps['accuracy'],
                'check_in_distance_meters' => $gps['distance'],
                'check_in_geofence_valid' => true,
                'check_in_late_minutes' => $lateMinutes,
                'setting_check_in_tolerance_minutes' => $toleranceMinutes,
                'status' => AttendanceRecord::STATUS_IN_PROGRESS,
                'snapshot_locked_at' => $locked->snapshot_locked_at ?? $now,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function checkOut(AttendanceRecord $record, array $payload, ?Carbon $now = null): AttendanceRecord
    {
        return DB::transaction(function () use ($record, $payload, $now) {
            $locked = AttendanceRecord::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $now ??= AttendanceClock::nowUtc();

            $this->assertWorking($locked);

            if ($locked->effectiveCheckInAt() === null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Anda belum melakukan check-in.',
                ]);
            }

            if ($locked->effectiveCheckOutAt() !== null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Anda sudah melakukan check-out untuk jadwal ini.',
                ]);
            }

            if ($locked->scheduled_end_at === null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Jadwal shift belum memiliki waktu selesai.',
                ]);
            }

            $opensAt = $locked->scheduled_end_at->copy()
                ->subMinutes((int) $locked->setting_early_checkout_tolerance_minutes);
            $closesAt = $locked->scheduled_end_at->copy()
                ->addMinutes((int) $locked->setting_checkout_close_minutes);

            if ($now->lessThan($opensAt)) {
                throw ValidationException::withMessages([
                    'attendance' => 'Check-out belum dapat dilakukan sebelum shift selesai.',
                ]);
            }

            if ($now->greaterThan($closesAt)) {
                throw ValidationException::withMessages([
                    'attendance' => 'Batas waktu check-out telah berakhir.',
                ]);
            }

            $gps = $this->validateGps($locked, $payload);

            $locked->forceFill([
                'check_out_at' => $now,
                'check_out_captured_at' => $this->parseCapturedAt($payload['captured_at'] ?? null),
                'check_out_latitude' => $gps['latitude'],
                'check_out_longitude' => $gps['longitude'],
                'check_out_accuracy_meters' => $gps['accuracy'],
                'check_out_distance_meters' => $gps['distance'],
                'check_out_geofence_valid' => true,
                'status' => AttendanceRecord::STATUS_COMPLETED,
                'snapshot_locked_at' => $locked->snapshot_locked_at ?? $now,
            ])->save();

            return $locked->refresh();
        });
    }

    private function assertWorking(AttendanceRecord $record): void
    {
        if ($record->shift_type !== Shift::TYPE_WORKING) {
            throw ValidationException::withMessages([
                'attendance' => 'Jadwal ini bukan shift kerja sehingga tidak memerlukan absensi.',
            ]);
        }

        if ($record->post_latitude === null || $record->post_longitude === null) {
            throw ValidationException::withMessages([
                'location' => 'Koordinat Pos Jaga belum tersedia. Silakan hubungi Admin Unit.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{latitude: float, longitude: float, accuracy: float, distance: float}
     */
    private function validateGps(AttendanceRecord $record, array $payload): array
    {
        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;
        $accuracy = $payload['accuracy'] ?? null;

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            throw ValidationException::withMessages([
                'location' => 'Lokasi perangkat tidak tersedia. Silakan ambil lokasi ulang.',
            ]);
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw ValidationException::withMessages([
                'location' => 'Koordinat lokasi perangkat tidak valid.',
            ]);
        }

        if (! is_numeric($accuracy) || (float) $accuracy < 0) {
            throw ValidationException::withMessages([
                'location' => 'Akurasi lokasi perangkat tidak tersedia. Silakan ambil lokasi ulang.',
            ]);
        }

        $accuracy = (float) $accuracy;

        if ($accuracy > (float) $record->setting_max_gps_accuracy_meters) {
            throw ValidationException::withMessages([
                'location' => 'Akurasi lokasi melebihi batas '
                    .$record->setting_max_gps_accuracy_meters.' meter. Silakan ambil lokasi ulang.',
            ]);
        }

        $distance = GeoDistance::meters(
            $latitude,
            $longitude,
            (float) $record->post_latitude,
            (float) $record->post_longitude
        );

        if ($distance > (float) $record->setting_gps_radius_meters) {
            throw ValidationException::withMessages([
                'location' => 'Lokasi Anda berada di luar radius '
                    .$record->setting_gps_radius_meters.' meter dari Pos Jaga. Silakan ambil lokasi ulang.',
            ]);
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'distance' => round($distance, 2),
        ];
    }

    private function parseCapturedAt(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || ! is_string($value)) {
            return null;
        }

        try {
            $hasOffset = preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $value) === 1;

            $parsed = $hasOffset
                ? Carbon::parse($value)->setTimezone(AttendanceClock::storageTimezone())
                : AttendanceClock::wibToUtc($value);

            return $parsed;
        } catch (\Throwable) {
            return null;
        }
    }
}
