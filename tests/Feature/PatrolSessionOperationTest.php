<?php

namespace Tests\Feature;

use App\Models\PatrolPoint;
use App\Models\PatrolScan;
use App\Models\PatrolSession;
use App\Models\PatrolSessionMember;
use App\Models\PatrolSessionPoint;
use App\Models\Schedule;
use App\Models\SchedulePattern;
use App\Models\SchedulePeriod;
use App\Models\SchedulePeriodMember;
use App\Models\Security;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use App\Support\AttendanceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatrolSessionOperationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_start_pending_session_and_sets_started_at_server_time(): void
    {
        $ctx = $this->makeSessionContext();
        Carbon::setTestNow($now = Carbon::parse('2026-11-15 08:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertOk()
            ->assertJsonPath('session.status', PatrolSession::STATUS_ACTIVE);

        $session = $ctx['session']->fresh();
        $this->assertSame(PatrolSession::STATUS_ACTIVE, $session->status);
        $this->assertTrue($session->started_at->equalTo($now->copy()->setTimezone(config('app.timezone'))));
        Carbon::setTestNow();
    }

    public function test_pending_session_cannot_start_before_shift_start(): void
    {
        $ctx = $this->makeSessionContext();
        Carbon::setTestNow(Carbon::parse('2026-11-15 06:59:59', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');

        $this->assertSame(PatrolSession::STATUS_PENDING, $ctx['session']->fresh()->status);
        $this->assertNull($ctx['session']->fresh()->started_at);
        Carbon::setTestNow();
    }

    public function test_pending_session_can_start_exactly_at_shift_start(): void
    {
        $ctx = $this->makeSessionContext();
        Carbon::setTestNow(Carbon::parse('2026-11-15 07:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertOk()
            ->assertJsonPath('session.status', PatrolSession::STATUS_ACTIVE);

        $this->assertSame(PatrolSession::STATUS_ACTIVE, $ctx['session']->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_pending_session_can_start_in_middle_of_shift(): void
    {
        $ctx = $this->makeSessionContext();
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertOk();

        $this->assertSame(PatrolSession::STATUS_ACTIVE, $ctx['session']->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_pending_session_cannot_start_exactly_at_shift_end(): void
    {
        $ctx = $this->makeSessionContext();
        Carbon::setTestNow(Carbon::parse('2026-11-15 15:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');

        $this->assertSame(PatrolSession::STATUS_PENDING, $ctx['session']->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_pending_session_cannot_start_for_future_work_date(): void
    {
        $ctx = $this->makeSessionContext(['date' => '2026-11-16']);
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');

        $this->assertSame(PatrolSession::STATUS_PENDING, $ctx['session']->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_pending_session_cannot_start_for_past_work_date_after_window(): void
    {
        $ctx = $this->makeSessionContext(['date' => '2026-11-14']);
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');

        $this->assertSame(PatrolSession::STATUS_PENDING, $ctx['session']->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_night_shift_can_start_before_midnight(): void
    {
        $ctx = $this->makeSessionContext([
            'date' => '2026-11-15',
            'shift_start_time' => '22:00:00',
            'shift_end_time' => '06:00:00',
            'shift_end_day_offset' => 1,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-15 22:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertOk();

        $this->assertSame(PatrolSession::STATUS_ACTIVE, $ctx['session']->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_night_shift_can_start_after_midnight_while_window_open(): void
    {
        $ctx = $this->makeSessionContext([
            'date' => '2026-11-15',
            'shift_start_time' => '22:00:00',
            'shift_end_time' => '06:00:00',
            'shift_end_day_offset' => 1,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-16 03:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertOk();

        $this->assertSame(PatrolSession::STATUS_ACTIVE, $ctx['session']->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_night_shift_cannot_start_exactly_at_end(): void
    {
        $ctx = $this->makeSessionContext([
            'date' => '2026-11-15',
            'shift_start_time' => '22:00:00',
            'shift_end_time' => '06:00:00',
            'shift_end_day_offset' => 1,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-16 06:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');

        $this->assertSame(PatrolSession::STATUS_PENDING, $ctx['session']->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_active_session_can_be_continued_after_shift_window_ends(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => Carbon::parse('2026-11-15 08:00:00')]);
        Carbon::setTestNow(Carbon::parse('2026-11-15 18:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertOk()
            ->assertJsonPath('session.status', PatrolSession::STATUS_ACTIVE);

        $this->assertSame(PatrolSession::STATUS_ACTIVE, $ctx['session']->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_start_uses_snapshot_shift_time_even_if_master_changes(): void
    {
        $ctx = $this->makeSessionContext([
            'date' => '2026-11-15',
            'shift_start_time' => '22:00:00',
            'shift_end_time' => '06:00:00',
            'shift_end_day_offset' => 1,
        ]);
        $ctx['shift']->update([
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'end_day_offset' => 0,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-16 03:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertOk();

        $this->assertSame(PatrolSession::STATUS_ACTIVE, $ctx['session']->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_start_rejected_safely_when_snapshot_shift_time_incomplete(): void
    {
        $ctx = $this->makeSessionContext(['shift_start_time' => null]);
        Carbon::setTestNow(Carbon::parse('2026-11-15 08:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');

        $this->assertSame(PatrolSession::STATUS_PENDING, $ctx['session']->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_second_member_can_access_active_session_without_changing_started_at(): void
    {
        $ctx = $this->makeSessionContext(['member_count' => 2]);
        $started = Carbon::parse('2026-11-15 08:00:00');
        $ctx['session']->update([
            'status' => PatrolSession::STATUS_ACTIVE,
            'started_at' => $started,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-15 18:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['users'][1])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertOk()
            ->assertJsonPath('message', 'Sesi patroli sudah aktif.')
            ->assertJsonPath('session.status', PatrolSession::STATUS_ACTIVE);

        $this->assertTrue($ctx['session']->fresh()->started_at->equalTo($started));
        Carbon::setTestNow();
    }

    public function test_non_member_cannot_start_session(): void
    {
        $ctx = $this->makeSessionContext();
        $outsider = $this->createSecurityUser('Outsider', 'SEC-999');

        $this->actingAs($outsider['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertForbidden();

        $this->assertSame(PatrolSession::STATUS_PENDING, $ctx['session']->fresh()->status);
    }

    public function test_user_without_security_relation_is_rejected(): void
    {
        $ctx = $this->makeSessionContext();
        $user = User::factory()->create(['role' => 'security']);

        $this->actingAs($user)
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertForbidden();
    }

    public function test_completed_session_cannot_be_started(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_COMPLETED]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');
    }

    public function test_incomplete_session_cannot_be_started(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_INCOMPLETE]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');
    }

    public function test_not_started_session_cannot_be_started(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_NOT_STARTED]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');
    }

    public function test_session_without_required_points_cannot_be_started(): void
    {
        $ctx = $this->makeSessionContext(['point_count' => 0]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');

        $this->assertSame(PatrolSession::STATUS_PENDING, $ctx['session']->fresh()->status);
    }

    public function test_active_member_can_scan_correct_qr(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);

        $response = $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'condition' => 'safe',
            ]);

        $response->assertOk();
        $this->assertDatabaseCount('patrol_scans', 1);
    }

    public function test_scan_creates_single_patrol_scan_record_with_server_identity_and_snapshots(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
        Carbon::setTestNow($now = Carbon::parse('2026-11-15 09:00:00'));
        $point = $ctx['points'][0];

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $point->patrol_point_qr_code,
                'condition' => 'safe',
            ])
            ->assertOk();

        $this->assertDatabaseCount('patrol_scans', 1);
        $scan = PatrolScan::query()->first();
        $this->assertSame($ctx['security']->id, $scan->security_id);
        $this->assertSame($ctx['security']->full_name, $scan->security_name);
        $this->assertSame($ctx['security']->registration_number, $scan->security_registration_number);
        $this->assertSame($point->patrol_point_name, $scan->patrol_point_name);
        $this->assertSame($point->patrol_point_qr_code, $scan->patrol_point_qr_code);
        $this->assertTrue($scan->scanned_at->equalTo($now));
        Carbon::setTestNow();
    }

    public function test_unknown_qr_is_rejected(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => (string) Str::uuid(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('qr_code');

        $this->assertDatabaseCount('patrol_scans', 0);
    }

    public function test_qr_from_other_post_is_rejected(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
        $otherPost = SecurityPost::query()->create(['unit_id' => $ctx['unit']->id, 'name' => 'Post B', 'post_code' => 'PB']);
        $otherPoint = PatrolPoint::query()->create([
            'security_post_id' => $otherPost->id,
            'name' => 'Other Post Point',
            'qr_code' => (string) Str::uuid(),
        ]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $otherPoint->qr_code,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('qr_code');

        $this->assertDatabaseCount('patrol_scans', 0);
    }

    public function test_qr_not_in_session_snapshot_is_rejected(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'point_count' => 1]);
        $extraPoint = PatrolPoint::query()->create([
            'security_post_id' => $ctx['post']->id,
            'name' => 'Extra Point',
            'qr_code' => (string) Str::uuid(),
        ]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $extraPoint->qr_code,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('qr_code');

        $this->assertDatabaseCount('patrol_scans', 0);
    }

    public function test_non_member_cannot_scan(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
        $outsider = $this->createSecurityUser('Outsider', 'SEC-999');

        $this->actingAs($outsider['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('patrol_scans', 0);
    }

    public function test_pending_session_does_not_accept_scan(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_PENDING]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');

        $this->assertDatabaseCount('patrol_scans', 0);
    }

    public function test_completed_session_does_not_accept_scan(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_COMPLETED, 'ended_at' => now()]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('patrol');

        $this->assertDatabaseCount('patrol_scans', 0);
    }

    // PATROL-14: multi-scan per titik
    public function test_same_satpam_can_scan_same_point_multiple_times_and_creates_new_records(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'point_count' => 2]);
        $qr = $ctx['points'][0]->patrol_point_qr_code;

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $qr, 'condition' => 'safe', 'idempotency_key' => 'a1'])
            ->assertOk();
        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $qr, 'condition' => 'safe', 'idempotency_key' => 'a2'])
            ->assertOk();

        $this->assertDatabaseCount('patrol_scans', 2);
        $this->assertSame(2, PatrolScan::query()->where('patrol_session_id', $ctx['session']->id)->where('patrol_point_id', $ctx['points'][0]->patrol_point_id)->count());
        // sesi belum complete karena titik 2 belum terpenuhi
        $this->assertSame(PatrolSession::STATUS_ACTIVE, $ctx['session']->fresh()->status);
    }

    public function test_different_member_can_scan_same_point_after_other_member(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'member_count' => 2, 'point_count' => 2]);
        $qr = $ctx['points'][0]->patrol_point_qr_code;

        $this->actingAs($ctx['users'][0])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $qr, 'condition' => 'safe', 'idempotency_key' => 'm1'])
            ->assertOk();
        $this->actingAs($ctx['users'][1])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $qr, 'condition' => 'safe', 'idempotency_key' => 'm2'])
            ->assertOk();

        $this->assertDatabaseCount('patrol_scans', 2);
        $ids = PatrolScan::query()->pluck('security_id')->sort()->values()->all();
        $this->assertSame(sort_array([$ctx['securities'][0]->id, $ctx['securities'][1]->id]), sort_array($ids));
    }

    public function test_preview_allows_already_scanned_point_and_creates_no_record(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'point_count' => 2]);
        $qr = $ctx['points'][0]->patrol_point_qr_code;
        PatrolScan::query()->create([
            'patrol_session_id' => $ctx['session']->id,
            'patrol_point_id' => $ctx['points'][0]->patrol_point_id,
            'security_id' => $ctx['security']->id,
            'patrol_point_name' => $ctx['points'][0]->patrol_point_name,
            'patrol_point_qr_code' => $qr,
            'security_name' => $ctx['security']->full_name,
            'security_registration_number' => $ctx['security']->registration_number,
            'scanned_at' => now(),
            'has_incident' => false,
        ]);
        $before = PatrolScan::query()->count();

        $res = $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/preview", ['qr_code' => $qr])
            ->assertOk();
        $res->assertJsonPath('preview.point.already_scanned', true);
        $this->assertSame($before, PatrolScan::query()->count());
    }

    public function test_idempotency_key_prevents_duplicate_record_for_same_request(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'point_count' => 2]);
        $qr = $ctx['points'][0]->patrol_point_qr_code;
        $payload = ['qr_code' => $qr, 'condition' => 'safe', 'idempotency_key' => 'same-key-123'];

        $this->actingAs($ctx['user'])->postJson("/app/patrol/{$ctx['session']->id}/scan", $payload)->assertOk()->assertJsonPath('idempotent', false);
        $this->actingAs($ctx['user'])->postJson("/app/patrol/{$ctx['session']->id}/scan", $payload)->assertOk()->assertJsonPath('idempotent', true);

        $this->assertDatabaseCount('patrol_scans', 1);
    }

    public function test_two_members_can_scan_different_points_in_same_session(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'member_count' => 2, 'point_count' => 2]);

        $this->actingAs($ctx['users'][0])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $ctx['points'][0]->patrol_point_qr_code, 'idempotency_key' => 'd1'])
            ->assertOk();

        $this->actingAs($ctx['users'][1])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $ctx['points'][1]->patrol_point_qr_code, 'idempotency_key' => 'd2'])
            ->assertOk();

        $this->assertDatabaseCount('patrol_scans', 2);
        $this->assertSame(PatrolSession::STATUS_COMPLETED, $ctx['session']->fresh()->status);
    }

    public function test_valid_coordinates_and_accuracy_are_stored(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'latitude' => 5.54829,
                'longitude' => 95.32375,
                'accuracy' => 12.5,
                'idempotency_key' => 'gps1',
            ])
            ->assertOk();

        $scan = PatrolScan::query()->first();
        $this->assertEquals('5.5482900', (string) $scan->latitude);
        $this->assertEquals('95.3237500', (string) $scan->longitude);
        $this->assertEquals('12.50', (string) $scan->accuracy);
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
        $qr = $ctx['points'][0]->patrol_point_qr_code;

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $qr, 'latitude' => 91, 'longitude' => 95])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $qr, 'latitude' => 5, 'longitude' => 181])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $qr, 'latitude' => 5, 'longitude' => 95, 'accuracy' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('accuracy');

        $this->assertDatabaseCount('patrol_scans', 0);
    }

    public function test_incident_without_description_is_rejected(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'has_incident' => true,
                'incident_description' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('incident_description');

        $this->assertDatabaseCount('patrol_scans', 0);
    }

    public function test_valid_incident_description_is_stored(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'has_incident' => true,
                'incident_description' => 'Pintu rusak',
            ])
            ->assertOk();

        $scan = PatrolScan::query()->first();
        $this->assertTrue($scan->has_incident);
        $this->assertSame('Pintu rusak', $scan->incident_description);
    }

    public function test_partial_unique_points_does_not_complete_session_even_with_many_scans(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'point_count' => 2]);

        $this->actingAs($ctx['user'])->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $ctx['points'][0]->patrol_point_qr_code, 'idempotency_key' => 'p1a'])->assertOk();
        $this->actingAs($ctx['user'])->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $ctx['points'][0]->patrol_point_qr_code, 'idempotency_key' => 'p1b'])->assertOk();
        $this->actingAs($ctx['user'])->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $ctx['points'][0]->patrol_point_qr_code, 'idempotency_key' => 'p1c'])->assertOk();

        $this->assertSame(3, PatrolScan::query()->count());
        $this->assertSame(PatrolSession::STATUS_ACTIVE, $ctx['session']->fresh()->status);
        $this->assertNull($ctx['session']->fresh()->ended_at);
    }

    public function test_session_completes_when_all_unique_required_points_have_at_least_one_scan(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => Carbon::parse('2026-11-15 08:00:00'), 'point_count' => 2]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $ctx['points'][0]->patrol_point_qr_code, 'idempotency_key' => 'c1'])
            ->assertOk();
        // scan ulang titik 1 tidak melengkapi sesi
        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $ctx['points'][0]->patrol_point_qr_code, 'idempotency_key' => 'c1b'])
            ->assertOk();
        $this->assertSame(PatrolSession::STATUS_ACTIVE, $ctx['session']->fresh()->status);

        Carbon::setTestNow($end = Carbon::parse('2026-11-15 09:30:00'));
        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $ctx['points'][1]->patrol_point_qr_code, 'idempotency_key' => 'c2'])
            ->assertOk()
            ->assertJsonPath('completed', true)
            ->assertJsonPath('session.status', PatrolSession::STATUS_COMPLETED);

        $session = $ctx['session']->fresh();
        $this->assertSame(PatrolSession::STATUS_COMPLETED, $session->status);
        $this->assertTrue($session->ended_at->equalTo($end));
        Carbon::setTestNow();
    }

    public function test_scan_after_completed_is_rejected(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'point_count' => 2]);

        $this->actingAs($ctx['user'])->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $ctx['points'][0]->patrol_point_qr_code, 'idempotency_key' => 'x1'])->assertOk();
        $this->actingAs($ctx['user'])->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $ctx['points'][1]->patrol_point_qr_code, 'idempotency_key' => 'x2'])->assertOk();
        $this->assertSame(PatrolSession::STATUS_COMPLETED, $ctx['session']->fresh()->status);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", ['qr_code' => $ctx['points'][0]->patrol_point_qr_code, 'idempotency_key' => 'x3'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('patrol_scans', 2);
    }

    public function test_failed_scan_leaves_no_partial_data(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'point_count' => 2]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'has_incident' => true,
                'incident_description' => '',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('patrol_scans', 0);
        $this->assertSame(PatrolSession::STATUS_ACTIVE, $ctx['session']->fresh()->status);
        $this->assertNull($ctx['session']->fresh()->ended_at);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeSessionContext(array $opts = []): array
    {
        $user = User::factory()->create(['role' => 'security']);
        $security = Security::query()->create([
            'user_id' => $user->id,
            'registration_number' => $opts['security_reg'] ?? 'SEC-001',
            'full_name' => $opts['security_name'] ?? 'Budi',
            'kta_issued_date' => '2024-01-01',
        ]);
        $unit = Unit::query()->create(['unit_name' => 'Unit A', 'unit_code' => 'UA', 'unit_level' => 'uid']);
        $post = SecurityPost::query()->create(['unit_id' => $unit->id, 'name' => 'Post A', 'post_code' => 'PA']);
        $team = SecurityTeam::query()->create(['security_post_id' => $post->id, 'name' => 'Team A', 'code' => 'TA', 'is_active' => true]);
        $shiftStart = array_key_exists('shift_start_time', $opts) ? $opts['shift_start_time'] : '07:00:00';
        $shiftEnd = array_key_exists('shift_end_time', $opts) ? $opts['shift_end_time'] : '15:00:00';
        $shiftOffset = $opts['shift_end_day_offset'] ?? 0;
        $shift = Shift::query()->create([
            'unit_id' => $unit->id,
            'name' => 'Pagi',
            'code' => 'P',
            'type' => 'working',
            'start_time' => $shiftStart ?? '07:00:00',
            'end_time' => $shiftEnd ?? '15:00:00',
            'end_day_offset' => $shiftOffset,
            'is_active' => true,
        ]);
        $period = SchedulePeriod::withoutEvents(fn () => SchedulePeriod::query()->create([
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'security_team_id' => $team->id,
            'name' => 'Period 1',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => 'active',
            'created_by' => $user->id,
        ]));
        $pattern = SchedulePattern::query()->create(['unit_id' => $unit->id, 'name' => 'Pattern A', 'cycle_length' => 1, 'is_active' => true]);

        $workDate = $opts['date'] ?? '2026-11-15';
        $session = PatrolSession::query()->create([
            'schedule_period_id' => $period->id,
            'security_team_id' => $team->id,
            'security_post_id' => $post->id,
            'unit_id' => $unit->id,
            'shift_id' => $shift->id,
            'work_date' => $workDate,
            'team_name' => 'Team A', 'team_code' => 'TA',
            'post_name' => 'Post A', 'post_code' => 'PA',
            'unit_name' => 'Unit A', 'unit_code' => 'UA',
            'shift_name' => 'Pagi', 'shift_code' => 'P', 'shift_type' => 'working',
            'shift_start_time' => $shiftStart,
            'shift_end_time' => $shiftEnd,
            'shift_end_day_offset' => $shiftOffset,
            'status' => $opts['status'] ?? PatrolSession::STATUS_PENDING,
            'started_at' => $opts['started_at'] ?? null,
            'ended_at' => $opts['ended_at'] ?? null,
        ]);

        $users = [$user];
        $securities = [$security];
        $memberCount = $opts['member_count'] ?? 1;
        for ($i = 1; $i <= $memberCount; $i++) {
            $u = $i === 1 ? $user : User::factory()->create(['role' => 'security']);
            $sec = $i === 1 ? $security : Security::query()->create([
                'user_id' => $u->id,
                'registration_number' => 'SEC-00'.($i),
                'full_name' => 'Satpam '.$i,
                'kta_issued_date' => '2024-01-01',
            ]);
            if ($i > 1) {
                $users[] = $u;
                $securities[] = $sec;
            }
            $pm = SchedulePeriodMember::query()->create([
                'schedule_period_id' => $period->id,
                'security_id' => $sec->id,
                'schedule_pattern_id' => $pattern->id,
                'start_offset' => 0,
            ]);
            $schedule = Schedule::withoutEvents(fn () => Schedule::query()->create([
                'schedule_period_id' => $period->id,
                'schedule_period_member_id' => $pm->id,
                'security_id' => $sec->id,
                'shift_id' => $shift->id,
                'schedule_pattern_id' => $pattern->id,
                'pattern_day_order' => 1,
                'date' => $workDate,
                'source' => 'system_generated',
                'created_by' => $user->id,
            ]));
            PatrolSessionMember::query()->create([
                'patrol_session_id' => $session->id,
                'schedule_id' => $schedule->id,
                'security_id' => $sec->id,
                'security_name' => $sec->full_name,
                'security_registration_number' => $sec->registration_number,
            ]);
        }

        $pointCount = array_key_exists('point_count', $opts) ? $opts['point_count'] : 1;
        $points = [];
        for ($p = 1; $p <= $pointCount; $p++) {
            $pp = PatrolPoint::query()->create([
                'security_post_id' => $post->id,
                'name' => 'Point '.$p,
                'qr_code' => (string) Str::uuid(),
            ]);
            $sp = PatrolSessionPoint::query()->create([
                'patrol_session_id' => $session->id,
                'patrol_point_id' => $pp->id,
                'patrol_point_name' => $pp->name,
                'patrol_point_qr_code' => $pp->qr_code,
                'sequence' => $p,
            ]);
            $points[] = $sp;
        }

        return compact('user', 'security', 'unit', 'post', 'team', 'shift', 'period', 'session', 'users', 'securities', 'points');
    }

    /**
     * @return array{user: User, security: Security}
     */
    private function createSecurityUser(string $name, string $reg): array
    {
        $user = User::factory()->create(['role' => 'security']);
        $security = Security::query()->create([
            'user_id' => $user->id,
            'registration_number' => $reg,
            'full_name' => $name,
            'kta_issued_date' => '2024-01-01',
        ]);

        return compact('user', 'security');
    }
}

function sort_array(array $a): array
{
    sort($a);

    return $a;
}
