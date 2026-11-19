<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Services\AttendanceActivityService;
use App\Services\AttendanceCorrectionService;
use App\Services\AttendanceStatusEvaluator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionTest extends AttendanceTestCase
{
    public function test_request_and_approve_applies_correction_and_locks_snapshot(): void
    {
        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-03 15:00:01');
        app(AttendanceStatusEvaluator::class)->markAbsent();
        $record->refresh();

        $service = app(AttendanceCorrectionService::class);
        $correction = $service->request($record, $this->security->user, [
            'reason' => 'Perangkat mati saat bertugas.',
            'requested_check_in_at' => Carbon::parse('2026-11-02 07:00:00'),
            'requested_check_out_at' => Carbon::parse('2026-11-02 15:05:00'),
            'requested_status' => AttendanceRecord::STATUS_COMPLETED,
        ]);

        $this->assertTrue($correction->isPending());

        $approved = $service->approve($correction, $this->admin, 'Bukti valid.');

        $this->assertSame(AttendanceCorrection::STATUS_APPROVED, $approved->status);
        $this->assertSame($this->admin->id, $approved->reviewed_by);
        $this->assertSame(AttendanceRecord::STATUS_ABSENT, $approved->before_status);
        $this->assertSame(AttendanceRecord::STATUS_COMPLETED, $approved->applied_status);
        $this->assertSame('2026-11-02 00:00:00', $approved->applied_check_in_at->toDateTimeString());

        $record->refresh();
        $this->assertSame(AttendanceRecord::STATUS_COMPLETED, $record->effectiveStatus());
        $this->assertSame('2026-11-02 00:00:00', $record->corrected_check_in_at->toDateTimeString());
        $this->assertSame('2026-11-02 08:05:00', $record->corrected_check_out_at->toDateTimeString());
        $this->assertNotNull($record->snapshot_locked_at);
        $this->assertNull($record->check_in_at);
    }

    public function test_only_one_pending_correction_per_record(): void
    {
        $record = $this->generateForSchedule();
        $service = app(AttendanceCorrectionService::class);

        $service->request($record, $this->security->user, [
            'reason' => 'Pertama.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ]);

        $this->expectException(ValidationException::class);

        $service->request($record->refresh(), $this->security->user, [
            'reason' => 'Kedua.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ]);
    }

    public function test_reject_records_decision_without_changing_record(): void
    {
        $record = $this->generateForSchedule();
        $service = app(AttendanceCorrectionService::class);
        $correction = $service->request($record, $this->security->user, [
            'reason' => 'Salah input.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ]);

        $rejected = $service->reject($correction, $this->admin, 'Tidak sesuai bukti.');

        $this->assertSame(AttendanceCorrection::STATUS_REJECTED, $rejected->status);
        $this->assertSame('Tidak sesuai bukti.', $rejected->review_note);
        $this->assertNotNull($rejected->reviewed_at);

        $record->refresh();
        $this->assertNull($record->corrected_status);
        $this->assertSame(AttendanceRecord::STATUS_SCHEDULED, $record->status);
    }

    public function test_cannot_approve_twice(): void
    {
        $record = $this->generateForSchedule();
        $service = app(AttendanceCorrectionService::class);
        $correction = $service->request($record, $this->security->user, [
            'reason' => 'Koreksi.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ]);

        $service->approve($correction, $this->admin);

        $this->expectException(ValidationException::class);

        $service->approve($correction->refresh(), $this->admin);
    }

    public function test_dynamic_status_requires_explicit_target_status(): void
    {
        $record = $this->generateForSchedule();

        $this->expectException(ValidationException::class);

        app(AttendanceCorrectionService::class)->request($record, $this->security->user, [
            'reason' => 'Lupa check-in.',
            'requested_check_in_at' => Carbon::parse('2026-11-02 07:05:00'),
        ]);
    }

    public function test_completed_target_requires_check_in_and_check_out(): void
    {
        $record = $this->generateForSchedule();

        $this->expectException(ValidationException::class);

        app(AttendanceCorrectionService::class)->request($record, $this->security->user, [
            'reason' => 'Salah status.',
            'requested_check_in_at' => Carbon::parse('2026-11-02 07:00:00'),
            'requested_status' => AttendanceRecord::STATUS_COMPLETED,
        ]);
    }

    public function test_approved_correction_overrides_check_in_status(): void
    {
        $record = $this->generateForSchedule();
        app(AttendanceActivityService::class)->checkIn($record, [
            'latitude' => 5.54829000,
            'longitude' => 95.32375000,
            'accuracy' => 5,
        ]);

        $service = app(AttendanceCorrectionService::class);
        $correction = $service->request($record->refresh(), $this->security->user, [
            'reason' => 'Check-in tercatat terlambat padahal tidak.',
            'requested_check_in_at' => Carbon::parse('2026-11-02 06:58:00'),
            'requested_status' => AttendanceRecord::STATUS_IN_PROGRESS,
        ]);

        $service->approve($correction, $this->admin);

        $record->refresh();
        $this->assertFalse($record->isLate());
        $this->assertSame('2026-11-01 23:58:00', $record->effectiveCheckInAt()->toDateTimeString());
        $this->assertNotNull($record->check_in_at);
        $this->assertNotSame($record->check_in_at->toDateTimeString(), $record->effectiveCheckInAt()->toDateTimeString());
    }
}
