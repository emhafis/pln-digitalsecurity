<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceGenerationAnomaly;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\Shift;
use App\Services\AttendanceCorrectionService;
use App\Services\AttendanceGenerator;

class AttendanceGeneratorTest extends AttendanceTestCase
{
    public function test_generates_working_and_day_off_records_with_snapshot(): void
    {
        $this->makeContext('2026-11-02', '2026-11-03');
        $dayOff = $this->makeDayOffShift();
        $this->makeSchedule($this->shift, '2026-11-02');
        $this->makeSchedule($dayOff, '2026-11-03');

        $result = $this->generate();

        $this->assertTrue($result['ready']);
        $this->assertSame(2, $result['created']);
        $this->assertSame(2, AttendanceRecord::query()->count());

        $working = AttendanceRecord::query()
            ->whereDate('work_date', '2026-11-02')
            ->firstOrFail();
        $this->assertSame(AttendanceRecord::STATUS_SCHEDULED, $working->status);
        $this->assertSame('2026-11-02 00:00:00', $working->scheduled_start_at->toDateTimeString());
        $this->assertSame('2026-11-02 08:00:00', $working->scheduled_end_at->toDateTimeString());
        $this->assertSame(50, $working->setting_gps_radius_meters);
        $this->assertNull($working->snapshot_locked_at);

        $off = AttendanceRecord::query()
            ->whereDate('work_date', '2026-11-03')
            ->firstOrFail();
        $this->assertSame(AttendanceRecord::STATUS_DAY_OFF, $off->status);
        $this->assertSame(Shift::TYPE_DAY_OFF, $off->shift_type);
        $this->assertNotNull($off->snapshot_locked_at);
        $this->assertNull($off->scheduled_start_at);
    }

    public function test_generation_is_idempotent(): void
    {
        $this->makeContext();
        $this->makeSchedule($this->shift);

        $first = $this->generate();
        $second = $this->generate();

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['synced']);
        $this->assertSame(1, $second['unchanged']);
        $this->assertSame(1, AttendanceRecord::query()->count());
    }

    public function test_generation_blocks_when_post_coordinates_missing_and_resolves_anomaly(): void
    {
        $this->makeContext();
        $this->post->update(['latitude' => null, 'longitude' => null]);
        $this->makeSchedule($this->shift);

        $result = $this->generate();

        $this->assertFalse($result['ready']);
        $this->assertSame(0, AttendanceRecord::query()->count());
        $this->assertSame(1, AttendanceGenerationAnomaly::query()
            ->where('anomaly_type', AttendanceGenerationAnomaly::TYPE_MISSING_POST_COORDINATES)
            ->whereNull('resolved_at')
            ->count());

        $this->post->update(['latitude' => 5.54829000, 'longitude' => 95.32375000]);

        $second = $this->generate();

        $this->assertTrue($second['ready']);
        $this->assertSame(1, AttendanceRecord::query()->count());
        $this->assertSame(0, AttendanceGenerationAnomaly::query()->active()->count());
        $this->assertNotNull(AttendanceGenerationAnomaly::query()->first()->resolved_at);
    }

    public function test_generation_blocks_invalid_working_time(): void
    {
        $this->makeContext();
        $this->shift->update(['start_time' => '15:00:00', 'end_time' => '07:00:00', 'end_day_offset' => 0]);
        $this->makeSchedule($this->shift);

        $result = $this->generate();

        $this->assertFalse($result['ready']);
        $this->assertSame(0, AttendanceRecord::query()->count());
        $this->assertDatabaseHas('attendance_generation_anomalies', [
            'anomaly_type' => AttendanceGenerationAnomaly::TYPE_INCOMPLETE_WORKING_TIME,
        ]);
    }

    public function test_generation_blocked_when_settings_missing(): void
    {
        $this->makeContext('2026-11-02', '2026-11-02', false);
        $this->makeSchedule($this->shift);

        $result = app(AttendanceGenerator::class)->generateAll($this->admin);

        $this->assertTrue($result['blocked_by_global']);
        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_locked_snapshot_is_not_synced_after_check_in(): void
    {
        $this->makeContext();
        $record = $this->generateForSchedule();

        $record->forceFill([
            'check_in_at' => now(),
            'status' => AttendanceRecord::STATUS_IN_PROGRESS,
            'snapshot_locked_at' => now(),
        ])->save();

        $this->shift->update(['start_time' => '08:00:00']);

        $result = $this->generate();

        $this->assertSame(0, $result['synced']);
        $record->refresh();
        $this->assertSame('07:00:00', substr((string) $record->shift_start_time, 0, 8));
        $this->assertSame('2026-11-02 00:00:00', $record->scheduled_start_at->toDateTimeString());
    }

    public function test_pending_correction_blocks_snapshot_sync(): void
    {
        $this->makeContext();
        $record = $this->generateForSchedule();

        app(AttendanceCorrectionService::class)->request($record, $this->security->user, [
            'reason' => 'Lupa check-in.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ]);

        $this->assertSame(1, AttendanceCorrection::query()
            ->where('status', AttendanceCorrection::STATUS_PENDING)
            ->count());

        $this->shift->update(['start_time' => '08:00:00']);

        $this->generate();

        $record->refresh();
        $this->assertSame('07:00:00', substr((string) $record->shift_start_time, 0, 8));
    }

    public function test_snapshot_syncs_when_record_has_no_activity(): void
    {
        $this->makeContext();
        $record = $this->generateForSchedule();

        $this->shift->update(['start_time' => '08:00:00']);
        $this->post->update(['name' => 'Pos Baru']);

        $result = $this->generate();

        $this->assertSame(1, $result['synced']);
        $record->refresh();
        $this->assertSame('08:00:00', substr((string) $record->shift_start_time, 0, 8));
        $this->assertSame('Pos Baru', $record->security_post_name);
    }

    public function test_generation_does_not_create_records_for_invalid_security(): void
    {
        $this->makeContext();
        $this->security->user->update(['is_active' => false]);
        $this->makeSchedule($this->shift);

        $result = $this->generate();

        $this->assertFalse($result['ready']);
        $this->assertSame(0, AttendanceRecord::query()->count());
        $this->assertDatabaseHas('attendance_generation_anomalies', [
            'anomaly_type' => AttendanceGenerationAnomaly::TYPE_INVALID_SECURITY_OR_MEMBERSHIP,
        ]);
    }

    public function test_unstarted_record_becomes_locked_day_off_when_shift_changes(): void
    {
        $this->makeContext();
        $record = $this->generateForSchedule();
        $dayOff = $this->makeDayOffShift();

        $this->schedule->update(['shift_id' => $dayOff->id]);

        $result = $this->generate();

        $this->assertSame(1, $result['synced']);
        $record->refresh();
        $this->assertSame(AttendanceRecord::STATUS_DAY_OFF, $record->status);
        $this->assertSame(Shift::TYPE_DAY_OFF, $record->shift_type);
        $this->assertNull($record->scheduled_start_at);
        $this->assertNotNull($record->snapshot_locked_at);
    }

    public function test_settings_snapshot_uses_current_values(): void
    {
        $this->makeContext();
        $settings = AttendanceSetting::current();
        $settings->update([
            'gps_radius_meters' => 75,
            'check_in_open_minutes' => 20,
        ]);

        $this->makeSchedule($this->shift);
        $this->generate();

        $record = AttendanceRecord::query()->firstOrFail();
        $this->assertSame(75, $record->setting_gps_radius_meters);
        $this->assertSame(20, $record->setting_check_in_open_minutes);
    }
}
