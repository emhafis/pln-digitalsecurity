<?php

namespace Tests\Feature\App;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Services\AttendanceCorrectionService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Attendance\AttendanceTestCase;

class AttendanceKehadiranTest extends AttendanceTestCase
{
    public function test_kehadiran_page_exposes_today_summary_and_scoped_history(): void
    {
        $this->generateForSchedule();

        $other = $this->createSecurity($this->unit, 'Satpam Lain');
        $otherMember = $this->period->addMember($other);
        $otherSchedule = $this->makeSchedule($this->shift, '2026-11-02', $otherMember, $other);
        $this->generate();
        $this->assertDatabaseHas('attendance_records', ['schedule_id' => $otherSchedule->id]);

        $this->actingAs($this->security->user)
            ->get('/app/attendance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Attendance/Index')
                ->where('state', 'record_available')
                ->where('operational_date.value', '2026-11-02')
                ->where('security.id', $this->security->id)
                ->where('shift.name', 'Pagi')
                ->where('attendance.status', AttendanceRecord::STATUS_PENDING)
                ->where('attendance.status_label', 'Belum Absen Masuk')
                ->where('attendance.work_date', '2026-11-02')
            );
    }

    public function test_history_page_only_shows_own_records_and_respects_month_filter(): void
    {
        $record = $this->generateForSchedule();

        $other = $this->createSecurity($this->unit, 'Satpam Lain');
        $otherMember = $this->period->addMember($other);
        $otherSchedule = $this->makeSchedule($this->shift, '2026-11-02', $otherMember, $other);
        $this->generate();

        $this->assertDatabaseHas('attendance_records', [
            'schedule_id' => $otherSchedule->id,
        ]);

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Attendance/History')
                ->where('filters.month', 11)
                ->where('filters.year', 2026)
                ->where('meta.total', 1)
                ->has('records', 1)
                ->where('records.0.id', $record->id)
            );

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=10&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records', 0)
                ->where('meta.total', 0)
            );
    }

    public function test_correction_page_lists_own_correctable_records(): void
    {
        $record = $this->generateForSchedule();

        $other = $this->createSecurity($this->unit, 'Satpam Lain');
        $otherMember = $this->period->addMember($other);
        $otherSchedule = $this->makeSchedule($this->shift, '2026-11-02', $otherMember, $other);
        $this->generate();
        $otherRecord = AttendanceRecord::query()->where('schedule_id', $otherSchedule->id)->firstOrFail();

        $this->actingAs($this->security->user)
            ->get('/app/attendance/correction')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Attendance/Correction')
                ->has('records', 1)
                ->where('records.0.id', $record->id)
                ->where('records.0.can_request_correction', true)
                ->has('correctable_statuses', 4)
            );

        $this->actingAs($this->security->user)
            ->get('/app/attendance/correction?record_id='.$otherRecord->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Attendance/Correction')
                ->where('selected_record_id', null)
                ->where('record', null)
            );
    }

    public function test_security_can_submit_correction_and_only_one_pending_is_allowed(): void
    {
        $record = $this->generateForSchedule();

        $payload = [
            'reason' => 'Koreksi status karena kendala perangkat.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ];

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/correction", $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('attendance_corrections', [
            'attendance_record_id' => $record->id,
            'requested_by' => $this->security->user->id,
            'status' => AttendanceCorrection::STATUS_PENDING,
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ]);

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/correction", $payload)
            ->assertSessionHasErrors('correction');
    }

    public function test_correction_validation_rejects_short_reason_and_invalid_time_order(): void
    {
        $record = $this->generateForSchedule();

        $this->actingAs($this->security->user)
            ->from('/app/attendance/correction')
            ->post("/app/attendance/{$record->id}/correction", [
                'reason' => '',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ])
            ->assertRedirect('/app/attendance/correction')
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('attendance_corrections', 0);

        $this->actingAs($this->security->user)
            ->from('/app/attendance/correction')
            ->post("/app/attendance/{$record->id}/correction", [
                'reason' => 'Waktu check-out keliru pada catatan kehadiran.',
                'requested_check_in_at' => '2026-11-02 15:00:00',
                'requested_check_out_at' => '2026-11-02 07:00:00',
                'requested_status' => AttendanceRecord::STATUS_COMPLETED,
            ])
            ->assertRedirect('/app/attendance/correction')
            ->assertSessionHasErrors('requested_check_out_at');

        $this->assertDatabaseCount('attendance_corrections', 0);
    }

    public function test_security_cannot_submit_correction_for_another_security_record(): void
    {
        $this->generateForSchedule();

        $other = $this->createSecurity($this->unit, 'Satpam Lain');
        $otherMember = $this->period->addMember($other);
        $otherSchedule = $this->makeSchedule($this->shift, '2026-11-02', $otherMember, $other);
        $this->generate();

        $otherRecord = AttendanceRecord::query()
            ->where('schedule_id', $otherSchedule->id)
            ->firstOrFail();

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$otherRecord->id}/correction", [
                'reason' => 'Mencoba mengoreksi catatan orang lain.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('attendance_corrections', 0);
    }

    public function test_correction_history_exposes_review_status_and_note(): void
    {
        $record = $this->generateForSchedule();
        $service = app(AttendanceCorrectionService::class);

        $correction = $service->request(
            $record,
            $this->security->user,
            [
                'reason' => 'Tidak sempat check-in karena tugas mendadak.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ]
        );

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.0.id', $record->id)
                ->where('records.0.has_pending_correction', true)
                ->where('records.0.can_request_correction', false)
                ->where('records.0.latest_correction_status', AttendanceCorrection::STATUS_PENDING)
                ->where('records.0.latest_correction_status_label', AttendanceCorrection::STATUS_LABELS[AttendanceCorrection::STATUS_PENDING])
            );

        $service->approve($correction, $this->admin, 'Disetujui oleh pengawas.');

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.0.has_pending_correction', false)
                ->where('records.0.can_request_correction', true)
                ->where('records.0.latest_correction_status', AttendanceCorrection::STATUS_APPROVED)
                ->where('records.0.latest_correction_status_label', AttendanceCorrection::STATUS_LABELS[AttendanceCorrection::STATUS_APPROVED])
            );

        $rejected = $service->request(
            $record->refresh(),
            $this->security->user,
            [
                'reason' => 'Pengajuan ulang setelah disetujui.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ]
        );
        $service->reject($rejected, $this->admin, 'Ditolak oleh pengawas.');

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.0.has_pending_correction', false)
                ->where('records.0.can_request_correction', true)
                ->where('records.0.latest_correction_status', AttendanceCorrection::STATUS_REJECTED)
                ->where('records.0.latest_correction_status_label', AttendanceCorrection::STATUS_LABELS[AttendanceCorrection::STATUS_REJECTED])
            );
    }
}
