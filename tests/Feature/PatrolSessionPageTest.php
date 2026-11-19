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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatrolSessionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_only_sessions_where_security_is_member(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 08:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-15']);
        $other = $this->makeSessionContext(['date' => '2026-11-15', 'label' => 'Other']);

        $this->actingAs($ctx['user'])
            ->get('/app/patrol')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/Patrol/Index')
                ->has('todaySessions', 1)
                ->where('todaySessions.0.id', $ctx['session']->id)
            );

        $this->actingAs($ctx['user'])
            ->get('/app/patrol')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('todaySessions.0.post_name', $ctx['session']->post_name)
            );

        $this->actingAs($ctx['user'])
            ->get('/app/patrol')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('otherSessions', 0)
            );

        Carbon::setTestNow();
    }

    public function test_index_groups_today_and_other_relevant_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 08:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $today = $this->makeSessionContext(['date' => '2026-11-15']);
        $future = $this->makeSessionContext(['date' => '2026-11-20', 'reuseUser' => $today]);

        $this->actingAs($today['user'])
            ->get('/app/patrol')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/Patrol/Index')
                ->has('todaySessions', 1)
                ->has('otherSessions', 1)
                ->where('todaySessions.0.id', $today['session']->id)
                ->where('otherSessions.0.id', $future['session']->id)
            );
        Carbon::setTestNow();
    }

    public function test_member_can_view_detail_and_non_member_forbidden(): void
    {
        $ctx = $this->makeSessionContext();
        $outsider = $this->createSecurityUser('Outsider', 'SEC-999');

        $this->actingAs($ctx['user'])
            ->get("/app/patrol/{$ctx['session']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/Patrol/Show')
                ->where('session.id', $ctx['session']->id)
            );

        $this->actingAs($outsider['user'])
            ->get("/app/patrol/{$ctx['session']->id}")
            ->assertForbidden();
    }

    public function test_detail_uses_snapshot_points_and_unique_fulfilled_progress(): void
    {
        $ctx = $this->makeSessionContext(['point_count' => 2, 'status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
        $sp1 = $ctx['points'][0];
        $sp2 = $ctx['points'][1];

        // dua scan pada titik yang sama -> titik terpenuhi tetap 1, total aktivitas 2
        PatrolScan::query()->create([
            'patrol_session_id' => $ctx['session']->id,
            'patrol_point_id' => $sp1->patrol_point_id,
            'security_id' => $ctx['security']->id,
            'patrol_point_name' => $sp1->patrol_point_name,
            'patrol_point_qr_code' => $sp1->patrol_point_qr_code,
            'security_name' => $ctx['security']->full_name,
            'security_registration_number' => $ctx['security']->registration_number,
            'scanned_at' => now()->subMinute(),
            'has_incident' => false,
            'idempotency_key' => 'k1',
        ]);
        PatrolScan::query()->create([
            'patrol_session_id' => $ctx['session']->id,
            'patrol_point_id' => $sp1->patrol_point_id,
            'security_id' => $ctx['security']->id,
            'patrol_point_name' => $sp1->patrol_point_name,
            'patrol_point_qr_code' => $sp1->patrol_point_qr_code,
            'security_name' => $ctx['security']->full_name,
            'security_registration_number' => $ctx['security']->registration_number,
            'scanned_at' => now(),
            'has_incident' => false,
            'idempotency_key' => 'k2',
        ]);

        $this->actingAs($ctx['user'])
            ->get("/app/patrol/{$ctx['session']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('session.required_points', 2)
                ->where('session.fulfilled_points', 1)
                ->where('session.scanned_points', 1)
                ->where('session.total_scans', 2)
                ->where('session.progress', 50)
                ->has('points', 2)
                ->where('points.0.sequence', $sp1->sequence)
                ->where('points.0.name', $sp1->patrol_point_name)
                ->where('points.0.scanned', true)
                ->where('points.0.scan_count', 2)
                ->where('points.1.sequence', $sp2->sequence)
                ->where('points.1.scanned', false)
                ->where('points.1.scan_count', 0)
            );
    }

    public function test_start_and_scan_endpoints_still_work_via_patrol07(): void
    {
        $ctx = $this->makeSessionContext(['point_count' => 1]);
        Carbon::setTestNow(Carbon::parse('2026-11-15 08:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/start")
            ->assertOk();

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'has_incident' => false,
            ])
            ->assertOk()
            ->assertJsonPath('completed', true);

        Carbon::setTestNow();
    }

    public function test_completed_session_rejects_new_scan(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_COMPLETED, 'ended_at' => now()]);

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('patrol_scans', 0);
    }

    public function test_scan_page_allows_preview_of_scanned_point(): void
    {
        $ctx = $this->makeSessionContext(['point_count' => 2, 'status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
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

        $this->actingAs($ctx['user'])
            ->postJson("/app/patrol/{$ctx['session']->id}/preview", ['qr_code' => $qr])
            ->assertOk()
            ->assertJsonPath('preview.point.already_scanned', true)
            ->assertJsonPath('preview.point.scan_count', 1);
    }

    public function test_valid_incident_photo_can_be_stored_with_compat_fields(): void
    {
        Storage::fake('local');
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
        $photo = UploadedFile::fake()->image('incident.jpg', 800, 600)->size(500);

        $this->actingAs($ctx['user'])
            ->post("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'has_incident' => '1',
                'incident_description' => 'Pintu rusak',
                'incident_photo' => $photo,
                'idempotency_key' => 'inc-1',
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $scan = PatrolScan::query()->with('photos')->first();
        $this->assertNotNull($scan->incident_photo_path);
        $this->assertTrue((bool) $scan->has_incident);
        $this->assertSame('Pintu rusak', $scan->incident_description);
        $this->assertCount(1, $scan->photos);
        $this->assertSame($scan->incident_photo_path, $scan->photos->first()->photo_path);
        Storage::disk('local')->assertExists($scan->incident_photo_path);
    }

    public function test_invalid_mime_and_oversize_photo_rejected(): void
    {
        Storage::fake('local');
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'point_count' => 2]);
        $bad = UploadedFile::fake()->create('incident.txt', 10, 'text/plain');

        $this->actingAs($ctx['user'])
            ->post("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'has_incident' => '1',
                'incident_description' => 'Desc',
                'incident_photo' => $bad,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $huge = UploadedFile::fake()->image('big.jpg')->size(5000);
        $this->actingAs($ctx['user'])
            ->post("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'has_incident' => '1',
                'incident_description' => 'Desc',
                'incident_photo' => $huge,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertDatabaseCount('patrol_scans', 0);
    }

    public function test_photo_cannot_be_sent_without_incident_and_description_required_when_incident(): void
    {
        Storage::fake('local');
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'point_count' => 2]);
        $photo = UploadedFile::fake()->image('incident.jpg', 600, 400)->size(300);

        $this->actingAs($ctx['user'])
            ->post("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'has_incident' => '0',
                'incident_photo' => $photo,
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->actingAs($ctx['user'])
            ->post("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'has_incident' => '1',
                'incident_description' => '',
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('incident_description');

        $this->assertDatabaseCount('patrol_scans', 0);
    }

    public function test_incident_photo_not_accessible_to_unauthorized_user(): void
    {
        Storage::fake('local');
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
        $photo = UploadedFile::fake()->image('incident.jpg', 600, 400)->size(300);

        $this->actingAs($ctx['user'])
            ->post("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $ctx['points'][0]->patrol_point_qr_code,
                'has_incident' => '1',
                'incident_description' => 'Ada insiden',
                'incident_photo' => $photo,
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $scan = PatrolScan::query()->first();
        $outsider = $this->createSecurityUser('Outsider', 'SEC-999');

        $this->actingAs($outsider['user'])
            ->get("/app/patrol/{$ctx['session']->id}/scans/{$scan->id}/photo")
            ->assertForbidden();

        $this->actingAs($ctx['user'])
            ->get("/app/patrol/{$ctx['session']->id}/scans/{$scan->id}/photo")
            ->assertOk();
    }

    public function test_upload_failure_does_not_leave_partial_scan_or_orphan_file(): void
    {
        Storage::fake('local');
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now(), 'point_count' => 2]);
        // QR valid format tapi bukan milik sesi -> validasi gagal di dalam transaksi setelah foto disimpan, harus cleanup
        $foreignQr = (string) Str::uuid();
        $photo = UploadedFile::fake()->image('incident.jpg', 600, 400)->size(300);

        $this->actingAs($ctx['user'])
            ->post("/app/patrol/{$ctx['session']->id}/scan", [
                'qr_code' => $foreignQr,
                'has_incident' => '1',
                'incident_description' => 'Desc',
                'incident_photo' => $photo,
                'idempotency_key' => 'orphan-1',
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $files = Storage::disk('local')->allFiles('patrol-incidents');
        $this->assertCount(0, $files);
        $this->assertDatabaseCount('patrol_scans', 0);
        $this->assertDatabaseCount('patrol_scan_photos', 0);
    }

    public function test_existing_historical_scans_without_photo_remain_valid(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_COMPLETED, 'ended_at' => now()]);
        $scan = PatrolScan::query()->create([
            'patrol_session_id' => $ctx['session']->id,
            'patrol_point_id' => $ctx['points'][0]->patrol_point_id,
            'security_id' => $ctx['security']->id,
            'patrol_point_name' => $ctx['points'][0]->patrol_point_name,
            'patrol_point_qr_code' => $ctx['points'][0]->patrol_point_qr_code,
            'security_name' => $ctx['security']->full_name,
            'security_registration_number' => $ctx['security']->registration_number,
            'scanned_at' => now(),
            'has_incident' => false,
        ]);

        $this->assertNull($scan->fresh()->incident_photo_path);
        $this->actingAs($ctx['user'])
            ->get("/app/patrol/{$ctx['session']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('points.0.scanned', true)
                ->where('points.0.photo_url', null)
            );
    }

    public function test_index_display_status_pending_future_is_menunggu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 08:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-16', 'status' => PatrolSession::STATUS_PENDING]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('otherSessions.0.display_status', 'pending')->where('otherSessions.0.display_status_label', 'Menunggu'));
        Carbon::setTestNow();
    }

    public function test_index_display_status_pending_before_start_today_is_menunggu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 06:59:59', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-15', 'status' => PatrolSession::STATUS_PENDING]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('todaySessions.0.display_status', 'pending')->where('todaySessions.0.display_status_label', 'Menunggu'));
        Carbon::setTestNow();
    }

    public function test_index_display_status_pending_exact_start_is_siap_dimulai(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 07:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-15', 'status' => PatrolSession::STATUS_PENDING]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('todaySessions.0.display_status', 'ready')->where('todaySessions.0.display_status_label', 'Siap Dimulai'));
        Carbon::setTestNow();
    }

    public function test_index_display_status_pending_mid_shift_is_siap_dimulai(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-15', 'status' => PatrolSession::STATUS_PENDING]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('todaySessions.0.display_status', 'ready'));
        Carbon::setTestNow();
    }

    public function test_index_display_status_pending_exact_end_is_terlewat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 15:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-15', 'status' => PatrolSession::STATUS_PENDING]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('todaySessions.0.display_status', 'missed')->where('todaySessions.0.display_status_label', 'Terlewat'));
        Carbon::setTestNow();
    }

    public function test_index_display_status_pending_past_date_is_terlewat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 08:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-14', 'status' => PatrolSession::STATUS_PENDING]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('otherSessions.0.display_status', 'missed'));
        Carbon::setTestNow();
    }

    public function test_index_display_status_night_shift_before_start_is_menunggu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 21:59:59', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-15', 'status' => PatrolSession::STATUS_PENDING, 'shift_start_time' => '22:00:00', 'shift_end_time' => '06:00:00', 'shift_end_day_offset' => 1]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('todaySessions.0.display_status', 'pending'));
        Carbon::setTestNow();
    }

    public function test_index_display_status_night_shift_after_midnight_open_is_siap_dimulai(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 03:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-15', 'status' => PatrolSession::STATUS_PENDING, 'shift_start_time' => '22:00:00', 'shift_end_time' => '06:00:00', 'shift_end_day_offset' => 1]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('otherSessions.0.display_status', 'ready'));
        Carbon::setTestNow();
    }

    public function test_index_display_status_night_shift_after_end_is_terlewat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 06:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-15', 'status' => PatrolSession::STATUS_PENDING, 'shift_start_time' => '22:00:00', 'shift_end_time' => '06:00:00', 'shift_end_day_offset' => 1]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('otherSessions.0.display_status', 'missed'));
        Carbon::setTestNow();
    }

    public function test_index_display_status_active_after_window_stays_berlangsung(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 18:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-15', 'status' => PatrolSession::STATUS_ACTIVE, 'started_at' => Carbon::parse('2026-11-15 08:00:00')]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('todaySessions.0.display_status', 'active')->where('todaySessions.0.display_status_label', 'Berlangsung'));
        Carbon::setTestNow();
    }

    public function test_index_display_status_completed_stays_selesai(): void
    {
        $ctx = $this->makeSessionContext(['date' => '2026-11-14', 'status' => PatrolSession::STATUS_COMPLETED, 'ended_at' => now()]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('otherSessions.0.display_status', 'completed')->where('otherSessions.0.display_status_label', 'Selesai'));
    }

    public function test_index_display_status_incomplete_stays_tidak_lengkap(): void
    {
        $ctx = $this->makeSessionContext(['date' => '2026-11-14', 'status' => PatrolSession::STATUS_INCOMPLETE]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('otherSessions.0.display_status', 'incomplete')->where('otherSessions.0.display_status_label', 'Tidak Lengkap'));
    }

    public function test_index_display_status_invalid_snapshot_is_safe_fallback(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 08:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-15', 'status' => PatrolSession::STATUS_PENDING, 'shift_start_time' => null, 'shift_end_time' => null]);
        $this->actingAs($ctx['user'])->get('/app/patrol')->assertOk()
            ->assertInertia(fn ($page) => $page->where('todaySessions.0.display_status', 'missed')->where('todaySessions.0.display_status_label', 'Terlewat'));
        Carbon::setTestNow();
    }

    public function test_index_paginates_other_sessions_ten_per_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 08:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $base = $this->makeSessionContext(['date' => '2026-11-15']);
        for ($i = 0; $i < 12; $i++) {
            $this->makeSessionContext([
                'date' => Carbon::parse('2026-12-01')->addDays($i)->toDateString(),
                'reuseUser' => $base,
            ]);
        }

        $this->actingAs($base['user'])
            ->get('/app/patrol')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('otherSessions', 10)
                ->where('otherSessionsMeta.current_page', 1)
                ->where('otherSessionsMeta.total', 12)
                ->where('otherSessionsMeta.last_page', 2)
                ->where('otherSessionsMeta.has_more', true)
            );

        $this->actingAs($base['user'])
            ->get('/app/patrol?page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('otherSessions', 2)
                ->where('otherSessionsMeta.current_page', 2)
                ->where('otherSessionsMeta.has_more', false)
            );

        Carbon::setTestNow();
    }

    public function test_index_filters_other_sessions_by_display_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 08:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $base = $this->makeSessionContext(['date' => '2026-11-15']);
        $this->makeSessionContext(['date' => '2026-11-14', 'reuseUser' => $base, 'status' => PatrolSession::STATUS_COMPLETED, 'ended_at' => now()->subDay()]);
        $this->makeSessionContext(['date' => '2026-11-13', 'reuseUser' => $base, 'status' => PatrolSession::STATUS_INCOMPLETE]);

        $this->actingAs($base['user'])
            ->get('/app/patrol?status=completed')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('otherSessions', 1)
                ->where('otherSessions.0.display_status', 'completed')
                ->where('otherSessionsMeta.status', 'completed')
                ->where('otherSessionsMeta.total', 1)
                ->where('otherStatusOptions.0.key', 'completed')
                ->where('otherStatusOptions.0.count', 1)
                ->where('otherStatusOptions.1.key', 'incomplete')
                ->where('otherStatusOptions.1.count', 1)
            );

        Carbon::setTestNow();
    }

    private function makeSessionContext(array $opts = []): array
    {
        $reuse = $opts['reuseUser'] ?? null;
        if ($reuse) {
            $user = $reuse['user'];
            $security = $reuse['security'];
        } else {
            $user = User::factory()->create(['role' => 'security']);
            $security = Security::query()->create([
                'user_id' => $user->id,
                'registration_number' => $opts['security_reg'] ?? 'SEC-'.Str::upper(Str::random(4)),
                'full_name' => $opts['security_name'] ?? 'Budi',
                'kta_issued_date' => '2024-01-01',
            ]);
        }

        $unit = Unit::query()->create(['unit_name' => 'Unit '.Str::random(3), 'unit_code' => 'U'.Str::random(3), 'unit_level' => 'uid']);
        $post = SecurityPost::query()->create(['unit_id' => $unit->id, 'name' => 'Post '.Str::random(3), 'post_code' => 'P'.Str::random(3)]);
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
        $date = $opts['date'] ?? '2026-11-15';
        $period = SchedulePeriod::withoutEvents(fn () => SchedulePeriod::query()->create([
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'security_team_id' => $team->id,
            'name' => 'Period '.Str::random(4),
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => 'active',
            'created_by' => $user->id,
        ]));
        $pattern = SchedulePattern::query()->create(['unit_id' => $unit->id, 'name' => 'Pattern '.Str::random(4), 'cycle_length' => 1, 'is_active' => true]);

        $session = PatrolSession::query()->create([
            'schedule_period_id' => $period->id,
            'security_team_id' => $team->id,
            'security_post_id' => $post->id,
            'unit_id' => $unit->id,
            'shift_id' => $shift->id,
            'work_date' => $date,
            'team_name' => 'Team A', 'team_code' => 'TA',
            'post_name' => $post->name, 'post_code' => $post->post_code,
            'unit_name' => $unit->unit_name, 'unit_code' => $unit->unit_code,
            'shift_name' => 'Pagi', 'shift_code' => 'P', 'shift_type' => 'working',
            'shift_start_time' => $shiftStart, 'shift_end_time' => $shiftEnd, 'shift_end_day_offset' => $shiftOffset,
            'status' => $opts['status'] ?? PatrolSession::STATUS_PENDING,
            'started_at' => $opts['started_at'] ?? null,
            'ended_at' => $opts['ended_at'] ?? null,
        ]);

        $pm = SchedulePeriodMember::query()->firstOrCreate(
            ['schedule_period_id' => $period->id, 'security_id' => $security->id],
            ['schedule_pattern_id' => $pattern->id, 'start_offset' => 0]
        );
        $schedule = Schedule::withoutEvents(fn () => Schedule::query()->create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $pm->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'schedule_pattern_id' => $pattern->id,
            'pattern_day_order' => 1,
            'date' => $date,
            'source' => 'system_generated',
            'created_by' => $user->id,
        ]));
        PatrolSessionMember::query()->create([
            'patrol_session_id' => $session->id,
            'schedule_id' => $schedule->id,
            'security_id' => $security->id,
            'security_name' => $security->full_name,
            'security_registration_number' => $security->registration_number,
        ]);

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

        return compact('user', 'security', 'unit', 'post', 'team', 'shift', 'period', 'session', 'points');
    }

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
