<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Services\AttendanceActivityService;
use App\Services\AttendanceStatusEvaluator;
use Illuminate\Validation\ValidationException;

class AttendanceActivityTest extends AttendanceTestCase
{
    protected function gps(array $overrides = []): array
    {
        return array_merge([
            'latitude' => 5.54829000,
            'longitude' => 95.32375000,
            'accuracy' => 5.0,
            'captured_at' => null,
        ], $overrides);
    }

    public function test_check_in_within_window_records_activity_and_locks_snapshot(): void
    {
        $record = $this->generateForSchedule();

        $result = app(AttendanceActivityService::class)->checkIn($record, $this->gps());

        $this->assertSame(AttendanceRecord::STATUS_IN_PROGRESS, $result->status);
        $this->assertNotNull($result->check_in_at);
        $this->assertSame('2026-11-01 23:50:00', $result->check_in_at->toDateTimeString());
        $this->assertTrue($result->check_in_geofence_valid);
        $this->assertLessThanOrEqual(1.0, (float) $result->check_in_distance_meters);
        $this->assertNotNull($result->snapshot_locked_at);
    }

    public function test_check_in_before_window_is_rejected(): void
    {
        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 06:40:00');

        $this->expectException(ValidationException::class);

        app(AttendanceActivityService::class)->checkIn($record, $this->gps());
    }

    public function test_check_in_after_shift_end_is_rejected(): void
    {
        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 15:01:00');

        $this->expectException(ValidationException::class);

        app(AttendanceActivityService::class)->checkIn($record, $this->gps());
    }

    public function test_check_in_outside_radius_is_rejected(): void
    {
        $record = $this->generateForSchedule();

        $this->expectException(ValidationException::class);

        app(AttendanceActivityService::class)->checkIn($record, $this->gps([
            'latitude' => 5.54930000,
        ]));
    }

    public function test_check_in_with_poor_accuracy_is_rejected(): void
    {
        $record = $this->generateForSchedule();

        $this->expectException(ValidationException::class);

        app(AttendanceActivityService::class)->checkIn($record, $this->gps([
            'accuracy' => 120.0,
        ]));
    }

    public function test_check_in_without_coordinates_is_rejected(): void
    {
        $record = $this->generateForSchedule();

        $this->expectException(ValidationException::class);

        app(AttendanceActivityService::class)->checkIn($record, [
            'latitude' => null,
            'longitude' => null,
            'accuracy' => 5,
        ]);
    }

    public function test_check_out_before_shift_end_is_rejected(): void
    {
        $record = $this->generateForSchedule();
        app(AttendanceActivityService::class)->checkIn($record, $this->gps());

        $this->travelToWib('2026-11-02 14:59:00');

        $this->expectException(ValidationException::class);

        app(AttendanceActivityService::class)->checkOut($record->refresh(), $this->gps());
    }

    public function test_check_out_after_close_is_rejected(): void
    {
        $record = $this->generateForSchedule();
        app(AttendanceActivityService::class)->checkIn($record, $this->gps());

        $this->travelToWib('2026-11-02 16:01:00');

        $this->expectException(ValidationException::class);

        app(AttendanceActivityService::class)->checkOut($record->refresh(), $this->gps());
    }

    public function test_check_out_within_window_completes_record(): void
    {
        $record = $this->generateForSchedule();
        app(AttendanceActivityService::class)->checkIn($record, $this->gps());

        $this->travelToWib('2026-11-02 15:10:00');

        $result = app(AttendanceActivityService::class)->checkOut($record->refresh(), $this->gps());

        $this->assertSame(AttendanceRecord::STATUS_COMPLETED, $result->status);
        $this->assertNotNull($result->check_out_at);
        $this->assertNotNull($result->snapshot_locked_at);
    }

    public function test_evaluator_marks_absent_after_deadline(): void
    {
        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-03 15:00:01');

        $count = app(AttendanceStatusEvaluator::class)->markAbsent();

        $this->assertSame(1, $count);
        $record->refresh();
        $this->assertSame(AttendanceRecord::STATUS_ABSENT, $record->status);
        $this->assertNotNull($record->absent_marked_at);
        $this->assertNotNull($record->snapshot_locked_at);
    }

    public function test_evaluator_does_not_mark_absent_before_deadline_or_when_checked_in(): void
    {
        $record = $this->generateForSchedule();

        app(AttendanceActivityService::class)->checkIn($record, $this->gps());

        $this->travelToWib('2026-11-03 14:59:59');

        $this->assertSame(0, app(AttendanceStatusEvaluator::class)->markAbsent());

        $this->travelToWib('2026-11-03 18:30:00');

        $this->assertSame(0, app(AttendanceStatusEvaluator::class)->markAbsent());
        $record->refresh();
        $this->assertSame(AttendanceRecord::STATUS_IN_PROGRESS, $record->status);
    }
}
