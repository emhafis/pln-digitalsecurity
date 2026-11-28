<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\Schedule;
use App\Models\SecurityPost;
use App\Models\User;
use App\Services\AttendanceCorrectionService;
use App\Services\AttendanceGenerator;
use App\Services\AttendanceStatusEvaluator;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

class AttendanceHttpTest extends AttendanceTestCase
{
    public function test_only_superadmin_can_update_attendance_settings(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);
        $payload = [
            'gps_radius_meters' => 60,
            'check_in_open_minutes' => 15,
            'late_tolerance_minutes' => 5,
            'early_checkout_tolerance_minutes' => 0,
            'checkout_close_minutes' => 60,
            'max_gps_accuracy_meters' => 40,
        ];

        $this->actingAs($superadmin)
            ->put('/admin/attendance-setting', $payload)
            ->assertRedirect('/admin/attendance-setting');

        $this->assertSame(60, AttendanceSetting::current()->gps_radius_meters);

        $this->makeContext();
        $this->actingAs($this->admin)
            ->put('/admin/attendance-setting', $payload)
            ->assertForbidden();
    }

    public function test_readiness_page_is_available_to_admin_but_not_security(): void
    {
        $this->makeContext();
        $this->makeSchedule($this->shift);

        $this->actingAs($this->admin)
            ->get('/admin/attendance/readiness')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Attendance/Readiness')
                ->where('report.global.ready', true)
                ->where('report.periods.0.schedule_count', 1)
            );

        $this->actingAs($this->security->user)
            ->get('/admin/attendance/readiness')
            ->assertForbidden();
    }

    public function test_period_without_schedules_is_not_ready(): void
    {
        $this->makeContext();

        $this->actingAs($this->admin)
            ->get('/admin/attendance/readiness')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.periods.0.schedule_count', 0)
                ->where('report.periods.0.ready', false)
                ->has('report.periods.0.issues', 1)
            );
    }

    public function test_readiness_anomaly_list_respects_period_filter(): void
    {
        $this->makeContext();
        $this->post->update(['latitude' => null, 'longitude' => null]);
        $this->makeSchedule($this->shift);
        $this->generate();

        $otherPeriod = $this->createPeriod(
            $this->unit,
            $this->post,
            $this->team,
            $this->admin,
            '2026-11-05',
            '2026-11-05',
            'draft',
            'Periode Lain'
        );

        $this->actingAs($this->admin)
            ->get('/admin/attendance/readiness')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('anomalies', 1));

        $this->actingAs($this->admin)
            ->get('/admin/attendance/readiness?period_id='.$otherPeriod->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('anomalies', 0));
    }

    public function test_record_list_is_scoped_to_the_admin_unit(): void
    {
        $this->makeContext();
        $this->makeSchedule($this->shift);
        $this->generate();

        $otherUnit = $this->createUnit();
        $otherPost = SecurityPost::factory()->create(['unit_id' => $otherUnit->id]);
        $otherTeam = $this->createTeam($otherPost);
        $otherAdmin = $this->createAdmin($otherUnit);
        $otherPeriod = $this->createPeriod(
            $otherUnit,
            $otherPost,
            $otherTeam,
            $otherAdmin,
            '2026-11-02',
            '2026-11-02',
            'active',
            'Unit Lain'
        );
        $otherSecurity = $this->createSecurity($otherUnit, 'Satpam Lain');
        $otherMember = $otherPeriod->addMember($otherSecurity);
        $otherShift = $this->createShift($otherUnit, 'Pagi', 'P');
        Schedule::create([
            'schedule_period_id' => $otherPeriod->id,
            'schedule_period_member_id' => $otherMember->id,
            'security_id' => $otherSecurity->id,
            'shift_id' => $otherShift->id,
            'pattern_day_order' => 1,
            'date' => '2026-11-02',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $otherAdmin->id,
        ]);
        app(AttendanceGenerator::class)->generatePeriod($otherPeriod, $otherAdmin);

        $this->actingAs($this->admin)
            ->get('/admin/attendance-record')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Attendance/Records/Index')
                ->where('filters.date_from', '2026-11-02')
                ->where('filters.date_to', '2026-11-02')
                ->has('records', 1)
                ->where('records.0.security_name', 'Satpam Absensi')
            );
    }

    public function test_attendance_record_index_defaults_to_today_wib_when_no_date_filter(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 23:30:00', 'UTC')); // 2026-11-03 WIB
        $this->makeContext('2026-11-02', '2026-11-03');
        $this->makeSchedule($this->shift, '2026-11-02');
        $this->makeSchedule($this->shift, '2026-11-03');
        $this->generate();

        $this->actingAs($this->admin)
            ->get('/admin/attendance-record')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.date_from', '2026-11-03')
                ->where('filters.date_to', '2026-11-03')
                ->has('records', 1)
                ->where('records.0.work_date', '2026-11-03')
            );

        Carbon::setTestNow();
    }

    public function test_attendance_record_index_specific_and_range_date_filters_work(): void
    {
        $this->makeContext('2026-11-02', '2026-11-04');
        $this->makeSchedule($this->shift, '2026-11-02');
        $this->makeSchedule($this->shift, '2026-11-03');
        $this->makeSchedule($this->shift, '2026-11-04');
        $this->generate();

        $this->actingAs($this->admin)
            ->get('/admin/attendance-record?date_from=2026-11-03&date_to=2026-11-03')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records', 1)
                ->where('records.0.work_date', '2026-11-03')
                ->where('filters.date_from', '2026-11-03')
                ->where('filters.date_to', '2026-11-03')
            );

        $this->actingAs($this->admin)
            ->get('/admin/attendance-record?date_from=2026-11-02&date_to=2026-11-03')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('records', 2));
    }

    public function test_security_can_see_own_attendance_and_check_in(): void
    {
        $record = $this->generateForSchedule();

        $this->actingAs($this->security->user)
            ->get('/app/attendance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Attendance/Index')
                ->where('attendance.id', $record->id)
                ->where('attendance.status', AttendanceRecord::STATUS_PENDING)
                ->where('state', 'record_available')
            );

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", [
                'latitude' => 5.54829000,
                'longitude' => 95.32375000,
                'accuracy' => 5,
            ])
            ->assertRedirect();

        $record->refresh();
        $this->assertSame(AttendanceRecord::STATUS_IN_PROGRESS, $record->status);
        $this->assertNotNull($record->check_in_at);
        $this->assertTrue((bool) $record->check_in_geofence_valid);
        $this->assertEquals(5.54829, (float) $record->check_in_latitude);
        $this->assertEquals(95.32375, (float) $record->check_in_longitude);
        $this->assertEquals(5.0, (float) $record->check_in_accuracy_meters);
        $this->assertNotNull($record->snapshot_locked_at);
    }

    public function test_check_in_is_on_time_at_window_boundaries_and_late_within_tolerance(): void
    {
        $this->makeContext('2026-11-02', '2026-11-04');

        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 06:45:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())
            ->assertRedirect();
        $record->refresh();
        $this->assertFalse($record->isLate());
        $this->assertSame('Tepat Waktu', $record->lateLabel());
        $this->assertSame(AttendanceRecord::STATUS_IN_PROGRESS, $record->status);

        $this->schedule = $this->makeSchedule($this->shift, '2026-11-03');
        $this->generate();
        $record2 = AttendanceRecord::query()->where('schedule_id', $this->schedule->id)->firstOrFail();
        $this->travelToWib('2026-11-03 07:05:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record2->id}/check-in", $this->validGpsPayload())
            ->assertRedirect();
        $record2->refresh();
        $this->assertTrue($record2->isLate());
        $this->assertSame(5, $record2->lateMinutes());

        $this->schedule = $this->makeSchedule($this->shift, '2026-11-04');
        $this->generate();
        $record3 = AttendanceRecord::query()->where('schedule_id', $this->schedule->id)->firstOrFail();
        $this->travelToWib('2026-11-04 07:06:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record3->id}/check-in", $this->validGpsPayload())
            ->assertSessionHasErrors('attendance');
        $this->assertNull($record3->refresh()->check_in_at);
    }

    public function test_check_in_after_shift_start_is_late_but_allowed_within_tolerance(): void
    {
        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 07:01:00');

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())
            ->assertRedirect();

        $record->refresh();
        $this->assertTrue($record->isLate());
        $this->assertSame(1, $record->lateMinutes());
        $this->assertSame('Terlambat 1 menit', $record->lateLabel());
    }

    public function test_check_in_fails_when_gps_outside_radius_or_accuracy_exceeds_limit_or_invalid(): void
    {
        $record = $this->generateForSchedule();

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", [
                'latitude' => 5.60000000,
                'longitude' => 95.40000000,
                'accuracy' => 5,
            ])->assertSessionHasErrors('location');
        $this->assertNull($record->refresh()->check_in_at);

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", [
                'latitude' => 5.54829000,
                'longitude' => 95.32375000,
                'accuracy' => 80,
            ])->assertSessionHasErrors('location');
        $this->assertNull($record->refresh()->check_in_at);

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", [
                'latitude' => 200,
                'longitude' => 95.32375000,
                'accuracy' => 5,
            ])->assertSessionHasErrors('latitude');

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", [
                'latitude' => 5.54829000,
                'longitude' => 95.32375000,
                'accuracy' => -1,
            ])->assertSessionHasErrors('accuracy');
    }

    public function test_check_in_fails_before_window_opens_or_after_tolerance_deadline(): void
    {
        $this->makeContext('2026-11-02', '2026-11-03');
        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 06:30:00');

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())
            ->assertSessionHasErrors('attendance');
        $this->assertNull($record->refresh()->check_in_at);

        $this->schedule = $this->makeSchedule($this->shift, '2026-11-03');
        $this->generate();
        $record2 = AttendanceRecord::query()->where('schedule_id', $this->schedule->id)->firstOrFail();
        $this->travelToWib('2026-11-03 07:16:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record2->id}/check-in", $this->validGpsPayload())
            ->assertSessionHasErrors('attendance');
        $this->assertNull($record2->refresh()->check_in_at);
    }

    public function test_check_in_tolerance_boundary_is_inclusive_and_lateness_is_from_shift_start(): void
    {
        AttendanceSetting::current()?->update(['late_tolerance_minutes' => 15]);
        $this->makeContext('2026-11-02', '2026-11-06');

        $cases = [
            ['2026-11-02', '07:00:00', true, 0, 'Tepat Waktu'],
            ['2026-11-03', '07:01:00', true, 1, 'Terlambat 1 menit'],
            ['2026-11-04', '07:10:00', true, 10, 'Terlambat 10 menit'],
            ['2026-11-05', '07:15:00', true, 15, 'Terlambat 15 menit'],
            ['2026-11-06', '07:16:00', false, null, null],
        ];

        foreach ($cases as [$date, $time, $allowed, $lateMinutes, $label]) {
            $this->schedule = $this->makeSchedule($this->shift, $date);
            $this->generate();
            $record = AttendanceRecord::query()->where('schedule_id', $this->schedule->id)->firstOrFail();
            $this->travelToWib($date.' '.$time);

            $response = $this->actingAs($this->security->user)
                ->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload());

            if ($allowed) {
                $response->assertRedirect();
                $record->refresh();
                $this->assertSame($lateMinutes, $record->lateMinutes());
                $this->assertSame($label, $record->lateLabel());
                $this->assertSame(15, $record->setting_check_in_tolerance_minutes);
            } else {
                $response->assertSessionHasErrors('attendance');
                $this->assertNull($record->refresh()->check_in_at);
            }
        }
    }

    public function test_check_in_cannot_be_duplicated_or_overwrite_existing(): void
    {
        $record = $this->generateForSchedule();

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())
            ->assertRedirect();
        $first = $record->refresh()->check_in_at;

        $this->travelToWib('2026-11-02 07:10:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())
            ->assertSessionHasErrors('attendance');

        $this->assertEquals($first, $record->refresh()->check_in_at);
    }

    private function validGpsPayload(): array
    {
        return [
            'latitude' => 5.54829000,
            'longitude' => 95.32375000,
            'accuracy' => 5,
        ];
    }

    public function test_security_can_check_out_successfully_and_store_evidence(): void
    {
        $record = $this->generateForSchedule();

        $this->travelToWib('2026-11-02 07:00:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())
            ->assertRedirect();

        $checkInSnapshot = $record->refresh()->only([
            'check_in_at', 'check_in_captured_at', 'check_in_latitude', 'check_in_longitude',
            'check_in_accuracy_meters', 'check_in_distance_meters', 'check_in_geofence_valid',
        ]);

        $this->travelToWib('2026-11-02 15:10:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-out", $this->validGpsPayload())
            ->assertRedirect();

        $record->refresh();
        $this->assertSame(AttendanceRecord::STATUS_COMPLETED, $record->status);
        $this->assertNotNull($record->check_out_at);
        $this->assertTrue((bool) $record->check_out_geofence_valid);
        $this->assertEquals(5.54829, (float) $record->check_out_latitude);
        $this->assertEquals(95.32375, (float) $record->check_out_longitude);
        $this->assertEquals(5.0, (float) $record->check_out_accuracy_meters);
        $this->assertNotNull($record->snapshot_locked_at);
        $this->assertSame($checkInSnapshot['check_in_at']?->toDateTimeString(), $record->check_in_at?->toDateTimeString());
        $this->assertEquals($checkInSnapshot['check_in_latitude'], $record->check_in_latitude);
        $this->assertEquals($checkInSnapshot['check_in_longitude'], $record->check_in_longitude);
        $this->assertEquals($checkInSnapshot['check_in_accuracy_meters'], $record->check_in_accuracy_meters);
        $this->assertEquals($checkInSnapshot['check_in_distance_meters'], $record->check_in_distance_meters);
        $this->assertEquals($checkInSnapshot['check_in_geofence_valid'], $record->check_in_geofence_valid);
    }

    public function test_check_out_succeeds_at_scheduled_end_and_deadline_boundaries(): void
    {
        $this->makeContext('2026-11-02', '2026-11-03');

        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 07:00:00');
        $this->actingAs($this->security->user)->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())->assertRedirect();
        $this->travelToWib('2026-11-02 15:00:00');
        $this->actingAs($this->security->user)->post("/app/attendance/{$record->id}/check-out", $this->validGpsPayload())->assertRedirect();
        $this->assertSame(AttendanceRecord::STATUS_COMPLETED, $record->refresh()->status);

        $this->schedule = $this->makeSchedule($this->shift, '2026-11-03');
        $this->generate();
        $record2 = AttendanceRecord::query()->where('schedule_id', $this->schedule->id)->firstOrFail();
        $this->travelToWib('2026-11-03 07:00:00');
        $this->actingAs($this->security->user)->post("/app/attendance/{$record2->id}/check-in", $this->validGpsPayload())->assertRedirect();
        $this->travelToWib('2026-11-03 16:00:00');
        $this->actingAs($this->security->user)->post("/app/attendance/{$record2->id}/check-out", $this->validGpsPayload())->assertRedirect();
        $this->assertSame(AttendanceRecord::STATUS_COMPLETED, $record2->refresh()->status);
        $this->assertNotNull($record2->check_out_at);
    }

    public function test_check_out_rejected_before_end_or_after_deadline(): void
    {
        $this->makeContext('2026-11-02', '2026-11-03');

        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 07:00:00');
        $this->actingAs($this->security->user)->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())->assertRedirect();
        $this->travelToWib('2026-11-02 14:59:00');
        $this->actingAs($this->security->user)->post("/app/attendance/{$record->id}/check-out", $this->validGpsPayload())->assertSessionHasErrors('attendance');
        $this->assertNull($record->refresh()->check_out_at);

        $this->schedule = $this->makeSchedule($this->shift, '2026-11-03');
        $this->generate();
        $record2 = AttendanceRecord::query()->where('schedule_id', $this->schedule->id)->firstOrFail();
        $this->travelToWib('2026-11-03 07:00:00');
        $this->actingAs($this->security->user)->post("/app/attendance/{$record2->id}/check-in", $this->validGpsPayload())->assertRedirect();
        $this->travelToWib('2026-11-03 16:01:00');
        $this->actingAs($this->security->user)->post("/app/attendance/{$record2->id}/check-out", $this->validGpsPayload())->assertSessionHasErrors('attendance');
        $this->assertNull($record2->refresh()->check_out_at);
    }

    public function test_check_out_rejected_without_check_in_or_when_already_checked_out(): void
    {
        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 15:10:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-out", $this->validGpsPayload())
            ->assertSessionHasErrors('attendance');
        $this->assertNull($record->refresh()->check_out_at);

        $this->travelToWib('2026-11-02 07:00:00');
        $this->actingAs($this->security->user)->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())->assertRedirect();
        $this->travelToWib('2026-11-02 15:10:00');
        $this->actingAs($this->security->user)->post("/app/attendance/{$record->id}/check-out", $this->validGpsPayload())->assertRedirect();
        $firstOut = $record->refresh()->check_out_at;

        $this->travelToWib('2026-11-02 15:20:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-out", $this->validGpsPayload())
            ->assertSessionHasErrors('attendance');
        $this->assertEquals($firstOut, $record->refresh()->check_out_at);
    }

    public function test_check_out_rejected_for_invalid_gps_outside_radius_or_bad_accuracy(): void
    {
        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 07:00:00');
        $this->actingAs($this->security->user)->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())->assertRedirect();
        $this->travelToWib('2026-11-02 15:10:00');

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-out", ['latitude' => 5.60000000, 'longitude' => 95.40000000, 'accuracy' => 5])
            ->assertSessionHasErrors('location');
        $this->assertNull($record->refresh()->check_out_at);

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-out", ['latitude' => 5.54829000, 'longitude' => 95.32375000, 'accuracy' => 80])
            ->assertSessionHasErrors('location');
        $this->assertNull($record->refresh()->check_out_at);

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-out", ['latitude' => 200, 'longitude' => 95.32375000, 'accuracy' => 5])
            ->assertSessionHasErrors('latitude');

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-out", ['latitude' => 5.54829000, 'longitude' => 95.32375000, 'accuracy' => -1])
            ->assertSessionHasErrors('accuracy');
    }

    public function test_security_cannot_check_out_another_security_record(): void
    {
        $this->generateForSchedule();

        $otherSecurity = $this->createSecurity($this->unit, 'Satpam Lain Checkout');
        $otherMember = $this->period->addMember($otherSecurity);
        $otherSchedule = $this->makeSchedule($this->shift, '2026-11-02', $otherMember, $otherSecurity);
        $this->generate();
        $otherRecord = AttendanceRecord::query()->where('schedule_id', $otherSchedule->id)->firstOrFail();

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$otherRecord->id}/check-out", $this->validGpsPayload())
            ->assertForbidden();
    }

    public function test_security_can_request_correction_for_dynamic_status(): void
    {
        $record = $this->generateForSchedule();

        $this->actingAs($this->security->user)
            ->get('/app/attendance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('attendance.status', AttendanceRecord::STATUS_PENDING)
                ->where('state', 'record_available')
            );

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/correction", [
                'reason' => 'Check-in tidak tercatat.',
                'requested_status' => AttendanceRecord::STATUS_ABSENT,
            ])
            ->assertRedirect();
    }

    public function test_record_list_status_filter_uses_effective_status(): void
    {
        $this->generateForSchedule();

        $this->actingAs($this->admin)
            ->get('/admin/attendance-record?status=pending&date_from=2026-11-02&date_to=2026-11-02')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records', 1)
                ->where('records.0.status', AttendanceRecord::STATUS_PENDING)
            );

        $this->actingAs($this->admin)
            ->get('/admin/attendance-record?status=scheduled&date_from=2026-11-02&date_to=2026-11-02')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('records', 0));

        $this->travelToWib('2026-11-02 06:30:00');

        $this->actingAs($this->admin)
            ->get('/admin/attendance-record?status=scheduled&date_from=2026-11-02&date_to=2026-11-02')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('records', 1)
                ->where('records.0.status', AttendanceRecord::STATUS_SCHEDULED)
            );

        $this->actingAs($this->admin)
            ->get('/admin/attendance-record?status=pending&date_from=2026-11-02&date_to=2026-11-02')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('records', 0));
    }

    public function test_record_becomes_absent_after_24h_deadline_effective_and_filter(): void
    {
        $this->generateForSchedule();

        $this->travelToWib('2026-11-03 15:00:00');
        $this->actingAs($this->admin)
            ->get('/admin/attendance-record?status=pending&date_from=2026-11-02&date_to=2026-11-02')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('records', 1)->where('records.0.status', AttendanceRecord::STATUS_PENDING));

        $this->travelToWib('2026-11-03 15:00:01');
        $this->actingAs($this->admin)
            ->get('/admin/attendance-record?status=pending&date_from=2026-11-02&date_to=2026-11-02')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('records', 0));
        $this->actingAs($this->admin)
            ->get('/admin/attendance-record?status=absent&date_from=2026-11-02&date_to=2026-11-02')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('records', 1)->where('records.0.status', AttendanceRecord::STATUS_ABSENT)->where('records.0.status_label', 'Tidak Hadir'));

        $record = AttendanceRecord::query()->where('schedule_id', $this->schedule->id)->firstOrFail();
        $before = [$record->check_in_at, $record->check_out_at, $record->check_in_latitude, $record->check_in_longitude];
        $count = app(AttendanceStatusEvaluator::class)->markAbsent();
        $this->assertSame(1, $count);
        $this->assertSame(0, app(AttendanceStatusEvaluator::class)->markAbsent());
        $record->refresh();
        $this->assertSame(AttendanceRecord::STATUS_ABSENT, $record->status);
        $this->assertSame($before[0], $record->check_in_at);
        $this->assertSame($before[1], $record->check_out_at);
        $this->assertSame($before[2], $record->check_in_latitude);
        $this->assertSame($before[3], $record->check_in_longitude);
    }

    public function test_night_shift_absence_deadline_respects_end_day_offset_snapshot(): void
    {
        $this->makeContext('2026-11-02', '2026-11-02');
        $night = $this->createShift($this->unit, 'Malam', 'M', [
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'end_day_offset' => 1,
        ]);
        $this->schedule = $this->makeSchedule($night, '2026-11-02');
        $this->generate();

        $this->travelToWib('2026-11-04 06:00:01');
        $this->actingAs($this->admin)
            ->get('/admin/attendance-record?status=absent&date_from=2026-11-02&date_to=2026-11-02')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('records', 1)->where('records.0.status', AttendanceRecord::STATUS_ABSENT));

        app(AttendanceStatusEvaluator::class)->markAbsent();
        $record = AttendanceRecord::query()->where('schedule_id', $this->schedule->id)->firstOrFail();
        $this->assertSame(AttendanceRecord::STATUS_ABSENT, $record->refresh()->status);
    }

    public function test_checked_in_record_is_not_marked_absent_after_deadline(): void
    {
        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 07:00:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())
            ->assertRedirect();

        $this->travelToWib('2026-11-03 18:00:00');
        $this->assertSame(0, app(AttendanceStatusEvaluator::class)->markAbsent());
        $record->refresh();
        $this->assertNotSame(AttendanceRecord::STATUS_ABSENT, $record->effectiveStatus());
    }

    public function test_overnight_shift_stays_active_until_checkout_next_day(): void
    {
        $this->makeContext('2026-11-27', '2026-11-28');
        $night = $this->createShift($this->unit, 'Malam', 'M', [
            'start_time' => '23:00:00',
            'end_time' => '08:00:00',
            'end_day_offset' => 1,
        ]);
        $day = $this->createShift($this->unit, 'Pagi Next', 'PN', [
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'end_day_offset' => 0,
        ]);
        $nightSchedule = $this->makeSchedule($night, '2026-11-27');
        $daySchedule = $this->makeSchedule($day, '2026-11-28');
        $this->generate();

        $nightRecord = AttendanceRecord::query()->where('schedule_id', $nightSchedule->id)->firstOrFail();

        $this->travelToWib('2026-11-27 23:00:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$nightRecord->id}/check-in", $this->validGpsPayload())
            ->assertRedirect();

        $this->travelToWib('2026-11-28 07:30:00');
        $this->actingAs($this->security->user)
            ->get('/app/attendance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Attendance/Index')
                ->where('state', 'record_available')
                ->where('attendance.id', $nightRecord->id)
                ->where('attendance.work_date', '2026-11-27')
                ->where('attendance.status_label', 'Hadir')
                ->where('shift.name', 'Malam')
            );

        $this->travelToWib('2026-11-28 08:00:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$nightRecord->id}/check-out", $this->validGpsPayload())
            ->assertRedirect();

        $nightRecord->refresh();
        $this->assertSame(AttendanceRecord::STATUS_COMPLETED, $nightRecord->status);
        $this->assertNotNull($nightRecord->check_out_at);

        $dayRecord = AttendanceRecord::query()->where('schedule_id', $daySchedule->id)->firstOrFail();
        $this->actingAs($this->security->user)
            ->get('/app/attendance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('attendance.id', $dayRecord->id)
                ->where('attendance.work_date', '2026-11-28')
                ->where('shift.name', 'Pagi Next')
            );
    }

    public function test_lateness_snapshot_is_not_recalculated_when_setting_changes(): void
    {
        AttendanceSetting::current()?->update(['late_tolerance_minutes' => 15]);
        $record = $this->generateForSchedule();
        $this->travelToWib('2026-11-02 07:10:00');
        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$record->id}/check-in", $this->validGpsPayload())
            ->assertRedirect();

        $record->refresh();
        $this->assertSame(10, $record->lateMinutes());

        AttendanceSetting::current()?->update(['late_tolerance_minutes' => 0]);
        $record->refresh();
        $this->assertSame(10, $record->lateMinutes());
        $this->assertSame('Terlambat 10 menit', $record->lateLabel());
    }

    public function test_security_cannot_act_on_another_record(): void
    {
        $this->generateForSchedule();

        $otherSecurity = $this->createSecurity($this->unit, 'Satpam Kedua');
        $otherMember = $this->period->addMember($otherSecurity);
        $otherSchedule = $this->makeSchedule($this->shift, '2026-11-03', $otherMember, $otherSecurity);
        $this->generate();
        $otherRecord = AttendanceRecord::query()->where('schedule_id', $otherSchedule->id)->firstOrFail();

        $this->actingAs($this->security->user)
            ->post("/app/attendance/{$otherRecord->id}/check-in", [
                'latitude' => 5.54829000,
                'longitude' => 95.32375000,
                'accuracy' => 5,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_approve_correction_only_within_unit_scope(): void
    {
        $record = $this->generateForSchedule();
        $correction = app(AttendanceCorrectionService::class)->request($record, $this->security->user, [
            'reason' => 'Koreksi.',
            'requested_status' => AttendanceRecord::STATUS_ABSENT,
        ]);

        $otherUnit = $this->createUnit();
        $otherAdmin = $this->createAdmin($otherUnit);

        $this->actingAs($otherAdmin)
            ->post("/admin/attendance-correction/{$correction->id}/approve", [])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post("/admin/attendance-correction/{$correction->id}/approve", ['review_note' => 'Disetujui.'])
            ->assertRedirect();

        $this->assertSame('approved', $correction->refresh()->status);
        $this->assertSame(AttendanceRecord::STATUS_ABSENT, $record->refresh()->effectiveStatus());
    }

    public function test_attendance_routes_require_authentication(): void
    {
        $this->get('/admin/attendance-record')->assertRedirect('/login');
        $this->get('/app/attendance')->assertRedirect('/login');
    }
}
