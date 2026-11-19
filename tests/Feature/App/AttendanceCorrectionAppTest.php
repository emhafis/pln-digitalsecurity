<?php

namespace Tests\Feature\App;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Services\AttendanceActivityService;
use App\Services\AttendanceCorrectionService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Attendance\AttendanceTestCase;

class AttendanceCorrectionAppTest extends AttendanceTestCase
{
    public function test_security_can_request_correction_for_own_record(): void
    {
        $record = $this->generateForSchedule();

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/correction", [
                'reason' => 'Lupa melakukan check-in.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_corrections', [
            'attendance_record_id' => $record->id,
            'requested_by' => $this->security->user->id,
            'requested_by_role' => $this->security->user->role,
            'reason' => 'Lupa melakukan check-in.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
            'status' => AttendanceCorrection::STATUS_PENDING,
        ]);
    }

    public function test_security_cannot_request_correction_for_other_security_record(): void
    {
        $this->generateForSchedule();

        $otherSecurity = $this->createSecurity($this->unit, 'Satpam Lain Koreksi');
        $otherMember = $this->period->addMember($otherSecurity);
        $otherSchedule = $this->makeSchedule($this->shift, '2026-11-02', $otherMember, $otherSecurity);
        $this->generate();
        $otherRecord = AttendanceRecord::query()->where('schedule_id', $otherSchedule->id)->firstOrFail();

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$otherRecord->id}/correction", [
                'reason' => 'Coba koreksi milik orang lain.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('attendance_corrections', 0);
    }

    public function test_empty_reason_and_invalid_payload_are_rejected(): void
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

        $this->actingAs($this->security->user)
            ->from('/app/attendance/correction')
            ->post("/app/attendance/{$record->id}/correction", [
                'reason' => 'Payload tidak valid',
                'requested_status' => 'invalid_status',
            ])
            ->assertRedirect('/app/attendance/correction')
            ->assertSessionHasErrors('requested_status');

        $this->assertDatabaseCount('attendance_corrections', 0);
    }

    public function test_second_request_while_pending_is_rejected(): void
    {
        $record = $this->generateForSchedule();

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/correction", [
                'reason' => 'Pengajuan pertama.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ])
            ->assertRedirect();

        $this->actingAs($this->security->user)
            ->from('/app/attendance/correction')
            ->post("/app/attendance/{$record->id}/correction", [
                'reason' => 'Pengajuan kedua.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ])
            ->assertRedirect('/app/attendance/correction')
            ->assertSessionHasErrors('correction');

        $this->assertDatabaseCount('attendance_corrections', 1);
    }

    public function test_can_request_again_after_previous_correction_completed(): void
    {
        $record = $this->generateForSchedule();
        $service = app(AttendanceCorrectionService::class);

        $first = $service->request($record, $this->security->user, [
            'reason' => 'Ditolak dulu.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ]);
        $service->reject($first, $this->admin, 'Ditolak.');

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/correction", [
                'reason' => 'Pengajuan ulang setelah ditolak.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('attendance_corrections', 2);

        $approvedRecord = $this->generateForScheduleForDate('2026-11-03');
        $second = $service->request($approvedRecord, $this->security->user, [
            'reason' => 'Akan disetujui.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ]);
        $service->approve($second, $this->admin, 'OK');

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$approvedRecord->id}/correction", [
                'reason' => 'Pengajuan ulang setelah disetujui.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, AttendanceCorrection::query()->where('attendance_record_id', $approvedRecord->id)->count());
    }

    public function test_pending_and_rejected_corrections_do_not_change_original_data(): void
    {
        $record = $this->generateForSchedule();
        $before = $this->recordSnapshot($record);

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/correction", [
                'reason' => 'Pending koreksi.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ])
            ->assertRedirect();

        $record->refresh();
        $this->assertSameSnapshot($before, $record);

        $pending = AttendanceCorrection::query()->firstOrFail();
        app(AttendanceCorrectionService::class)->reject($pending, $this->admin, 'Ditolak.');

        $record->refresh();
        $this->assertSameSnapshot($before, $record);
    }

    public function test_approved_correction_applies_corrected_fields_without_changing_original_evidence(): void
    {
        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 07:00:00');
        app(AttendanceActivityService::class)->checkIn($record, $this->validGpsPayload());
        $this->travelToWib('2026-11-02 15:10:00');
        app(AttendanceActivityService::class)->checkOut($record->refresh(), $this->validGpsPayload());

        $record->refresh();
        $original = [
            'check_in_at' => $record->check_in_at?->toDateTimeString(),
            'check_out_at' => $record->check_out_at?->toDateTimeString(),
            'check_in_latitude' => $record->check_in_latitude,
            'check_in_longitude' => $record->check_in_longitude,
            'check_in_accuracy_meters' => $record->check_in_accuracy_meters,
            'check_in_distance_meters' => $record->check_in_distance_meters,
            'check_in_geofence_valid' => $record->check_in_geofence_valid,
            'check_out_latitude' => $record->check_out_latitude,
            'check_out_longitude' => $record->check_out_longitude,
            'check_out_accuracy_meters' => $record->check_out_accuracy_meters,
            'check_out_distance_meters' => $record->check_out_distance_meters,
            'check_out_geofence_valid' => $record->check_out_geofence_valid,
        ];

        $correction = app(AttendanceCorrectionService::class)->request($record, $this->security->user, [
            'reason' => 'Waktu tercatat keliru.',
            'requested_check_in_at' => Carbon::parse('2026-11-02 07:05:00', 'Asia/Jakarta'),
            'requested_check_out_at' => Carbon::parse('2026-11-02 15:05:00', 'Asia/Jakarta'),
            'requested_status' => AttendanceRecord::STATUS_COMPLETED,
        ]);

        app(AttendanceCorrectionService::class)->approve($correction, $this->admin, 'Disetujui.');

        $record->refresh();
        $this->assertNotNull($record->corrected_check_in_at);
        $this->assertNotNull($record->corrected_check_out_at);
        $this->assertSame(AttendanceRecord::STATUS_COMPLETED, $record->corrected_status);

        $this->assertSame($original['check_in_at'], $record->check_in_at?->toDateTimeString());
        $this->assertSame($original['check_out_at'], $record->check_out_at?->toDateTimeString());
        $this->assertEquals($original['check_in_latitude'], $record->check_in_latitude);
        $this->assertEquals($original['check_in_longitude'], $record->check_in_longitude);
        $this->assertEquals($original['check_in_accuracy_meters'], $record->check_in_accuracy_meters);
        $this->assertEquals($original['check_in_distance_meters'], $record->check_in_distance_meters);
        $this->assertEquals($original['check_in_geofence_valid'], $record->check_in_geofence_valid);
        $this->assertEquals($original['check_out_latitude'], $record->check_out_latitude);
        $this->assertEquals($original['check_out_longitude'], $record->check_out_longitude);
        $this->assertEquals($original['check_out_accuracy_meters'], $record->check_out_accuracy_meters);
        $this->assertEquals($original['check_out_distance_meters'], $record->check_out_distance_meters);
        $this->assertEquals($original['check_out_geofence_valid'], $record->check_out_geofence_valid);
    }

    public function test_history_shows_correction_status_and_eligibility(): void
    {
        $record = $this->generateForSchedule();

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records', 1)
                ->where('records.0.id', $record->id)
                ->where('records.0.has_pending_correction', false)
                ->where('records.0.can_request_correction', true)
                ->where('records.0.latest_correction_status', null)
            );

        $correction = app(AttendanceCorrectionService::class)->request($record, $this->security->user, [
            'reason' => 'Menunggu keputusan.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ]);

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.0.has_pending_correction', true)
                ->where('records.0.can_request_correction', false)
                ->where('records.0.latest_correction_status', AttendanceCorrection::STATUS_PENDING)
            );

        app(AttendanceCorrectionService::class)->reject($correction, $this->admin, 'Ditolak.');

        $this->actingAs($this->security->user)
            ->get('/app/attendance/history?month=11&year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.0.has_pending_correction', false)
                ->where('records.0.can_request_correction', true)
                ->where('records.0.latest_correction_status', AttendanceCorrection::STATUS_REJECTED)
            );
    }

    public function test_correction_page_marks_ineligible_record_and_backend_rejects(): void
    {
        $record = $this->generateForSchedule();
        app(AttendanceCorrectionService::class)->request($record, $this->security->user, [
            'reason' => 'Masih pending.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ]);

        $this->actingAs($this->security->user)
            ->get('/app/attendance/correction?record_id='.$record->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Attendance/Correction')
                ->where('record.id', $record->id)
                ->where('record.has_pending_correction', true)
                ->where('record.can_request_correction', false)
            );

        $this->actingAs($this->security->user)
            ->from('/app/attendance/correction?record_id='.$record->id)
            ->post("/app/attendance/{$record->id}/correction", [
                'reason' => 'Coba lagi saat pending.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ])
            ->assertRedirect('/app/attendance/correction?record_id='.$record->id)
            ->assertSessionHasErrors('correction');
    }

    private function generateForScheduleForDate(string $date): AttendanceRecord
    {
        $this->schedule = $this->makeSchedule($this->shift, $date);
        $this->generate();

        return AttendanceRecord::query()->where('schedule_id', $this->schedule->id)->firstOrFail();
    }

    private function validGpsPayload(): array
    {
        return ['latitude' => 5.54829000, 'longitude' => 95.32375000, 'accuracy' => 5];
    }

    /**
     * @return array<string, mixed>
     */
    private function recordSnapshot(AttendanceRecord $record): array
    {
        return $record->only([
            'status', 'check_in_at', 'check_out_at', 'check_in_latitude', 'check_in_longitude',
            'check_in_accuracy_meters', 'check_in_distance_meters', 'check_in_geofence_valid',
            'check_out_latitude', 'check_out_longitude', 'check_out_accuracy_meters',
            'check_out_distance_meters', 'check_out_geofence_valid', 'corrected_check_in_at',
            'corrected_check_out_at', 'corrected_status', 'snapshot_locked_at',
        ]);
    }

    private function assertSameSnapshot(array $before, AttendanceRecord $record): void
    {
        $after = $this->recordSnapshot($record->refresh());
        foreach ($before as $key => $value) {
            $a = $value instanceof Carbon ? $value->toDateTimeString() : $value;
            $b = $after[$key] instanceof Carbon ? $after[$key]->toDateTimeString() : $after[$key];
            $this->assertSame($a, $b, "Field {$key} changed");
        }
    }
}
