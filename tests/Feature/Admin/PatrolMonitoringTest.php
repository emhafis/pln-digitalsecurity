<?php

namespace Tests\Feature\Admin;

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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatrolMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_security_cannot_access_admin_patrol_monitoring(): void
    {
        $ctx = $this->makeSessionContext();

        $this->get('/admin/patrol-session')->assertRedirect('/login');
        $this->get("/admin/patrol-session/{$ctx['session']->id}")->assertRedirect('/login');

        $securityUser = $this->createSecurityUser('Satpam Tamu', 'SEC-GST-'.Str::upper(Str::random(3)));
        $this->actingAs($securityUser['user'])->get('/admin/patrol-session')->assertForbidden();
        $this->actingAs($securityUser['user'])->get("/admin/patrol-session/{$ctx['session']->id}")->assertForbidden();
    }

    public function test_admin_can_open_session_in_scope_and_index_links_to_detail(): void
    {
        $ctx = $this->makeSessionContext();

        $this->actingAs($ctx['admin'])
            ->get('/admin/patrol-session?unit_id='.$ctx['unit']->id.'&date_from='.$ctx['session']->work_date->toDateString().'&date_to='.$ctx['session']->work_date->toDateString())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/PatrolMonitoring/Index')
                ->has('sessions', 1)
                ->where('sessions.0.id', $ctx['session']->id)
            );

        $this->actingAs($ctx['admin'])
            ->get("/admin/patrol-session/{$ctx['session']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/PatrolMonitoring/Show')
                ->where('session.id', $ctx['session']->id)
                ->where('session.unit_name', $ctx['session']->unit_name)
            );
    }

    public function test_index_defaults_to_today_wib_when_no_date_filter_provided(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 23:30:00', 'UTC')); // 2026-11-16 WIB
        $ctx = $this->makeSessionContext(['date' => '2026-11-16']);
        $this->makeSessionContext(['date' => '2026-11-15', 'reuseAdmin' => $ctx]);

        $this->actingAs($ctx['admin'])
            ->get('/admin/patrol-session?unit_id='.$ctx['unit']->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/PatrolMonitoring/Index')
                ->where('filters.date_from', '2026-11-16')
                ->where('filters.date_to', '2026-11-16')
                ->has('sessions', 1)
                ->where('sessions.0.work_date', '2026-11-16')
            );

        Carbon::setTestNow();
    }

    public function test_index_specific_and_range_date_filters_still_work(): void
    {
        $ctx = $this->makeSessionContext(['date' => '2026-11-15']);
        $ctx2 = $this->makeSessionContext(['date' => '2026-11-17', 'reuseAdmin' => $ctx]);
        $ctx3 = $this->makeSessionContext(['date' => '2026-11-20', 'reuseAdmin' => $ctx]);

        $this->actingAs($ctx['admin'])
            ->get('/admin/patrol-session?unit_id='.$ctx['unit']->id.'&date_from=2026-11-17&date_to=2026-11-17')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sessions', 1)
                ->where('sessions.0.id', $ctx2['session']->id)
                ->where('filters.date_from', '2026-11-17')
                ->where('filters.date_to', '2026-11-17')
            );

        $this->actingAs($ctx['admin'])
            ->get('/admin/patrol-session?unit_id='.$ctx['unit']->id.'&date_from=2026-11-15&date_to=2026-11-18')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sessions', 2));
    }

    public function test_index_reset_returns_to_today_default(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', AttendanceClock::OPERATIONAL_TIMEZONE));
        $ctx = $this->makeSessionContext(['date' => '2026-11-15']);
        $this->makeSessionContext(['date' => '2026-11-20', 'reuseAdmin' => $ctx]);

        $this->actingAs($ctx['admin'])
            ->get('/admin/patrol-session?unit_id='.$ctx['unit']->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.date_from', '2026-11-15')
                ->where('filters.date_to', '2026-11-15')
                ->has('sessions', 1)
                ->where('sessions.0.work_date', '2026-11-15')
            );

        Carbon::setTestNow();
    }

    public function test_admin_cannot_open_session_or_photo_out_of_scope(): void
    {
        $ctx = $this->makeSessionContext();
        $other = $this->makeSessionContext();

        Storage::fake('local');
        $scan = $this->createIncidentScan($ctx, withPhoto: true);

        $this->actingAs($other['admin'])
            ->get("/admin/patrol-session/{$ctx['session']->id}")
            ->assertForbidden();

        $this->actingAs($other['admin'])
            ->get("/admin/patrol-session/{$ctx['session']->id}/scans/{$scan->id}/photo")
            ->assertForbidden();
    }

    public function test_superadmin_can_access_session_detail_globally(): void
    {
        $ctx = $this->makeSessionContext();
        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);

        $this->actingAs($superadmin)
            ->get("/admin/patrol-session/{$ctx['session']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/PatrolMonitoring/Show')
                ->where('session.id', $ctx['session']->id)
            );
    }

    public function test_detail_uses_snapshots_and_sequence_order_even_if_master_changes(): void
    {
        $ctx = $this->makeSessionContext(['point_count' => 2]);
        $sp1 = $ctx['points'][0];
        $sp2 = $ctx['points'][1];

        $sp1->update(['sequence' => 10, 'patrol_point_name' => 'Snapshot Titik B']);
        $sp2->update(['sequence' => 1, 'patrol_point_name' => 'Snapshot Titik A']);
        $sp1->update(['sequence' => 2]);
        $ctx['session']->update([
            'unit_name' => 'Unit Snapshot', 'unit_code' => 'USNAP',
            'post_name' => 'Pos Snapshot', 'post_code' => 'PSNAP',
            'team_name' => 'Regu Snapshot', 'team_code' => 'RSNAP',
            'shift_name' => 'Shift Snapshot', 'shift_code' => 'SSNAP',
        ]);

        $ctx['unit']->update(['unit_name' => 'Unit Master Baru', 'unit_code' => 'UMB']);
        $ctx['post']->update(['name' => 'Pos Master Baru', 'post_code' => 'PMB']);
        $ctx['team']->update(['name' => 'Regu Master Baru', 'code' => 'RMB']);
        $ctx['shift']->update(['name' => 'Shift Master Baru', 'code' => 'SMB']);
        PatrolPoint::query()->whereKey($sp1->patrol_point_id)->update(['name' => 'Master Point Baru']);

        $this->actingAs($ctx['admin'])
            ->get("/admin/patrol-session/{$ctx['session']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('session.unit_name', 'Unit Snapshot')
                ->where('session.post_name', 'Pos Snapshot')
                ->where('session.team_name', 'Regu Snapshot')
                ->where('session.shift_name', 'Shift Snapshot')
                ->has('points', 2)
                ->where('points.0.sequence', 1)
                ->where('points.0.name', 'Snapshot Titik A')
                ->where('points.1.sequence', 2)
                ->where('points.1.name', 'Snapshot Titik B')
            );
    }

    public function test_scan_status_identity_and_nullable_gps_are_correct(): void
    {
        $ctx = $this->makeSessionContext(['point_count' => 2, 'status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
        $sp1 = $ctx['points'][0];

        PatrolScan::query()->create([
            'patrol_session_id' => $ctx['session']->id,
            'patrol_point_id' => $sp1->patrol_point_id,
            'security_id' => $ctx['security']->id,
            'patrol_point_name' => $sp1->patrol_point_name,
            'patrol_point_qr_code' => $sp1->patrol_point_qr_code,
            'security_name' => 'Scanner Snapshot',
            'security_registration_number' => 'REG-SNAP',
            'scanned_at' => Carbon::parse('2026-11-15 09:10:00'),
            'latitude' => null,
            'longitude' => null,
            'accuracy' => null,
            'has_incident' => false,
        ]);

        $this->actingAs($ctx['admin'])
            ->get("/admin/patrol-session/{$ctx['session']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('points', 2)
                ->where('points.0.fulfilled', true)
                ->where('points.0.total_scans', 1)
                ->where('points.0.member_cells.0.scans.0.security_name', 'Scanner Snapshot')
                ->where('points.0.member_cells.0.scans.0.security_registration_number', 'REG-SNAP')
                ->where('points.0.member_cells.0.scans.0.latitude', null)
                ->where('points.0.member_cells.0.scans.0.longitude', null)
                ->where('points.0.member_cells.0.scans.0.accuracy', null)
                ->where('points.1.fulfilled', false)
                ->where('points.1.total_scans', 0)
                ->where('session.required_points', 2)
                ->where('session.fulfilled_points', 1)
            );
    }

    public function test_incident_without_photo_is_displayed_and_member_activity_reflects_scans(): void
    {
        $ctx = $this->makeSessionContext(['point_count' => 1, 'status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);

        PatrolScan::query()->create([
            'patrol_session_id' => $ctx['session']->id,
            'patrol_point_id' => $ctx['points'][0]->patrol_point_id,
            'security_id' => $ctx['security']->id,
            'patrol_point_name' => $ctx['points'][0]->patrol_point_name,
            'patrol_point_qr_code' => $ctx['points'][0]->patrol_point_qr_code,
            'security_name' => $ctx['security']->full_name,
            'security_registration_number' => $ctx['security']->registration_number,
            'scanned_at' => Carbon::parse('2026-11-15 09:20:00'),
            'has_incident' => true,
            'incident_description' => 'Lampu padam',
            'incident_photo_path' => null,
        ]);

        $this->actingAs($ctx['admin'])
            ->get("/admin/patrol-session/{$ctx['session']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('incidents', 1)
                ->where('incidents.0.description', 'Lampu padam')
                ->where('incidents.0.has_photo', false)
                ->where('incidents.0.reported_by_name', $ctx['security']->full_name)
                ->has('members', 1)
                ->where('members.0.scan_count', 1)
                ->where('members.0.scan_activity', 'Sudah melakukan scan (1 aktivitas)')
            );
    }

    public function test_authorized_admin_can_access_photo_and_cross_session_or_missing_file_is_safe(): void
    {
        $ctx = $this->makeSessionContext(['point_count' => 2, 'status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
        Storage::fake('local');
        $scan = $this->createIncidentScan($ctx, withPhoto: true, pointIndex: 0);

        $this->actingAs($ctx['admin'])
            ->get("/admin/patrol-session/{$ctx['session']->id}/scans/{$scan->id}/photo")
            ->assertOk();

        $other = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
        $otherScan = $this->createIncidentScan($other, withPhoto: true);
        $this->actingAs($ctx['admin'])
            ->get("/admin/patrol-session/{$ctx['session']->id}/scans/{$otherScan->id}/photo")
            ->assertNotFound();

        $scan2 = $this->createIncidentScan($ctx, withPhoto: true, pointIndex: 1);
        Storage::disk('local')->delete($scan2->incident_photo_path);
        $this->actingAs($ctx['admin'])
            ->get("/admin/patrol-session/{$ctx['session']->id}/scans/{$scan2->id}/photo")
            ->assertNotFound();
    }

    public function test_detail_and_photo_endpoints_do_not_mutate_data(): void
    {
        $ctx = $this->makeSessionContext(['status' => PatrolSession::STATUS_ACTIVE, 'started_at' => now()]);
        Storage::fake('local');
        $scan = $this->createIncidentScan($ctx, withPhoto: true);

        $beforeSession = $ctx['session']->fresh()->toArray();
        $beforeScan = $scan->fresh()->toArray();
        $counts = [
            'sessions' => PatrolSession::count(),
            'members' => PatrolSessionMember::count(),
            'points' => PatrolSessionPoint::count(),
            'scans' => PatrolScan::count(),
        ];

        $this->actingAs($ctx['admin'])->get("/admin/patrol-session/{$ctx['session']->id}")->assertOk();
        $this->actingAs($ctx['admin'])->get("/admin/patrol-session/{$ctx['session']->id}/scans/{$scan->id}/photo")->assertOk();

        $this->assertSame($beforeSession, $ctx['session']->fresh()->toArray());
        $this->assertSame($beforeScan, $scan->fresh()->toArray());
        $this->assertSame($counts['sessions'], PatrolSession::count());
        $this->assertSame($counts['members'], PatrolSessionMember::count());
        $this->assertSame($counts['points'], PatrolSessionPoint::count());
        $this->assertSame($counts['scans'], PatrolScan::count());
    }

    private function createIncidentScan(array $ctx, bool $withPhoto, int $pointIndex = 0): PatrolScan
    {
        $path = null;
        if ($withPhoto) {
            $path = 'patrol-incidents/'.Str::uuid().'.jpg';
            Storage::disk('local')->put($path, 'fake-image');
        }

        $point = $ctx['points'][$pointIndex];

        return PatrolScan::query()->create([
            'patrol_session_id' => $ctx['session']->id,
            'patrol_point_id' => $point->patrol_point_id,
            'security_id' => $ctx['security']->id,
            'patrol_point_name' => $point->patrol_point_name,
            'patrol_point_qr_code' => $point->patrol_point_qr_code,
            'security_name' => $ctx['security']->full_name,
            'security_registration_number' => $ctx['security']->registration_number,
            'scanned_at' => Carbon::parse('2026-11-15 10:00:00'),
            'has_incident' => true,
            'incident_description' => 'Kejadian',
            'incident_photo_path' => $path,
        ]);
    }

    private function makeSessionContext(array $opts = []): array
    {
        if (! empty($opts['reuseAdmin'])) {
            $base = $opts['reuseAdmin'];
            $unit = $base['unit'];
            $post = $base['post'];
            $team = $base['team'];
            $shift = $base['shift'];
            $admin = $base['admin'];
        } else {
            $unit = Unit::query()->create([
                'unit_name' => 'Unit '.Str::random(4),
                'unit_code' => 'U'.Str::upper(Str::random(3)),
                'unit_level' => 'uid',
            ]);
            $post = SecurityPost::query()->create([
                'unit_id' => $unit->id,
                'name' => 'Post '.Str::random(3),
                'post_code' => 'P'.Str::upper(Str::random(3)),
            ]);
            $team = SecurityTeam::query()->create([
                'security_post_id' => $post->id,
                'name' => 'Team A',
                'code' => 'TA',
                'is_active' => true,
            ]);
            $shift = Shift::query()->create([
                'unit_id' => $unit->id,
                'name' => 'Pagi',
                'code' => 'P',
                'type' => 'working',
                'start_time' => '07:00:00',
                'end_time' => '15:00:00',
                'end_day_offset' => 0,
                'is_active' => true,
            ]);

            $admin = User::factory()->create(['role' => 'admin', 'unit_id' => $unit->id]);
        }

        $sec = $this->createSecurityUser('Budi '.Str::random(3), 'SEC-'.Str::upper(Str::random(4)));
        $user = $sec['user'];
        $security = $sec['security'];

        $date = $opts['date'] ?? '2026-11-15';
        $period = SchedulePeriod::withoutEvents(fn () => SchedulePeriod::query()->create([
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'security_team_id' => $team->id,
            'name' => 'Period '.Str::random(4),
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => 'active',
            'created_by' => $admin->id,
        ]));
        $pattern = SchedulePattern::query()->create([
            'unit_id' => $unit->id,
            'name' => 'Pattern '.Str::random(4),
            'cycle_length' => 1,
            'is_active' => true,
        ]);

        $session = PatrolSession::query()->create([
            'schedule_period_id' => $period->id,
            'security_team_id' => $team->id,
            'security_post_id' => $post->id,
            'unit_id' => $unit->id,
            'shift_id' => $shift->id,
            'work_date' => $date,
            'team_name' => $team->name,
            'team_code' => $team->code,
            'post_name' => $post->name,
            'post_code' => $post->post_code,
            'unit_name' => $unit->unit_name,
            'unit_code' => $unit->unit_code,
            'shift_name' => $shift->name,
            'shift_code' => $shift->code,
            'shift_type' => 'working',
            'shift_start_time' => '07:00:00',
            'shift_end_time' => '15:00:00',
            'shift_end_day_offset' => 0,
            'status' => $opts['status'] ?? PatrolSession::STATUS_PENDING,
            'started_at' => $opts['started_at'] ?? null,
            'ended_at' => $opts['ended_at'] ?? null,
        ]);

        $pm = SchedulePeriodMember::query()->create([
            'schedule_period_id' => $period->id,
            'security_id' => $security->id,
            'schedule_pattern_id' => $pattern->id,
            'start_offset' => 0,
        ]);
        $schedule = Schedule::withoutEvents(fn () => Schedule::query()->create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $pm->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'schedule_pattern_id' => $pattern->id,
            'pattern_day_order' => 1,
            'date' => $date,
            'source' => 'system_generated',
            'created_by' => $admin->id,
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

        return compact('admin', 'user', 'security', 'unit', 'post', 'team', 'shift', 'period', 'session', 'points');
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
