<?php

namespace Tests\Feature\Admin;

use App\Exports\PatrolReportExport;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatrolReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_security_cannot_access_report(): void
    {
        $ctx = $this->makeContext();

        $this->get('/admin/patrol-report')->assertRedirect('/login');
        $this->get('/admin/patrol-report/export')->assertRedirect('/login');

        $sec = $this->createSecurityUser('Satpam X', 'SEC-X'.Str::upper(Str::random(3)));
        $this->actingAs($sec['user'])->get('/admin/patrol-report?unit_id='.$ctx['unit']->id)->assertForbidden();
        $this->actingAs($sec['user'])->get('/admin/patrol-report/export?unit_id='.$ctx['unit']->id)->assertForbidden();
    }

    public function test_admin_scope_and_superadmin_unit_selection_and_out_of_scope_filters(): void
    {
        $a = $this->makeContext(['label' => 'A']);
        $b = $this->makeContext(['label' => 'B']);

        $this->createSession($a, ['status' => PatrolSession::STATUS_COMPLETED, 'points' => 2, 'scans' => 2]);
        $this->createSession($b, ['status' => PatrolSession::STATUS_COMPLETED, 'points' => 2, 'scans' => 2]);

        $this->actingAs($a['admin'])
            ->get('/admin/patrol-report')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/PatrolReport/Index')
                ->where('summary.total_sessions', 1)
                ->has('by_unit', 1)
                ->where('by_unit.0.unit_name', $a['unit']->unit_name)
            );

        $this->actingAs($a['admin'])
            ->get('/admin/patrol-report?unit_id='.$b['unit']->id)
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('summary.total_sessions', 1)
                ->where('by_unit.0.unit_name', $a['unit']->unit_name)
            );

        $super = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);
        $this->actingAs($super)
            ->get('/admin/patrol-report')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('requires_unit_selection', true)->where('summary.total_sessions', 0));

        $this->actingAs($super)
            ->get('/admin/patrol-report?unit_id='.$b['unit']->id)
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('requires_unit_selection', false)
                ->where('summary.total_sessions', 1)
                ->where('by_unit.0.unit_name', $b['unit']->unit_name)
            );
    }

    public function test_statistics_filters_percentages_incidents_and_security_activity(): void
    {
        $ctx = $this->makeContext();
        $sec1 = $ctx['security'];
        $sec2arr = $this->createSecurityUser('Satpam Dua', 'SEC-DUA');

        $this->createSession($ctx, [
            'date' => '2026-11-10', 'status' => PatrolSession::STATUS_COMPLETED,
            'points' => 2, 'scans' => [
                ['sec' => $sec1, 'incident' => true],
                ['sec' => $sec1, 'incident' => false],
            ],
        ]);
        $this->createSession($ctx, [
            'date' => '2026-11-11', 'status' => PatrolSession::STATUS_ACTIVE,
            'extra_members' => [$sec2arr['security']],
            'points' => 3, 'scans' => [
                ['sec' => $sec2arr['security'], 'incident' => false],
            ],
        ]);
        $this->createSession($ctx, ['date' => '2026-11-12', 'status' => PatrolSession::STATUS_INCOMPLETE, 'points' => 1, 'scans' => []]);
        $this->createSession($ctx, ['date' => '2026-11-13', 'status' => PatrolSession::STATUS_PENDING, 'points' => 2, 'scans' => []]);
        $this->createSession($ctx, ['date' => '2026-11-14', 'status' => PatrolSession::STATUS_NOT_STARTED, 'points' => 1, 'scans' => []]);
        $this->createSession($ctx, ['date' => '2026-11-15', 'status' => PatrolSession::STATUS_COMPLETED, 'points' => 0, 'scans' => []]);

        $this->actingAs($ctx['admin'])
            ->get('/admin/patrol-report?unit_id='.$ctx['unit']->id.'&date_from=2026-11-10&date_to=2026-11-15')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('summary.total_sessions', 6)
                ->where('summary.completed_sessions', 2)
                ->where('summary.active_sessions', 1)
                ->where('summary.incomplete_sessions', 1)
                ->where('summary.not_started_sessions', 2)
                ->where('summary.total_required_points', 9)
                ->where('summary.total_fulfilled_points', 3)
                ->where('summary.total_scanned_points', 3)
                ->where('summary.total_scan_activity', 3)
                ->where('summary.completion_percentage', 33.33)
                ->where('summary.total_incidents', 1)
            );

        $this->actingAs($ctx['admin'])
            ->get('/admin/patrol-report?unit_id='.$ctx['unit']->id.'&date_from=2026-11-10&date_to=2026-11-10')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('summary.total_sessions', 1)->where('summary.total_fulfilled_points', 2)->where('summary.total_scan_activity', 2));

        $this->actingAs($ctx['admin'])
            ->get('/admin/patrol-report?unit_id='.$ctx['unit']->id.'&status=active')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('summary.total_sessions', 1)->where('summary.active_sessions', 1));

        $page = $this->actingAs($ctx['admin'])->get('/admin/patrol-report?unit_id='.$ctx['unit']->id);
        $page->assertInertia(function ($p) use ($sec1, $sec2arr) {
            $rows = collect($p->toArray()['props']['security_activity']);
            $r1 = $rows->firstWhere('security_id', $sec1->id);
            $r2 = $rows->firstWhere('security_id', $sec2arr['security']->id);
            $p->where('by_unit.0.total_sessions', 6)->where('by_unit.0.completion_percentage', 33.33);
            $this->assertSame(6, $r1['assigned_sessions']);
            $this->assertSame(1, $r1['participated_sessions']);
            $this->assertSame(2, $r1['scan_activity_count']);
            $this->assertSame(2, $r1['scanned_points']);
            $this->assertSame(1, $r1['incident_reports']);
            $this->assertSame(1, $r2['assigned_sessions']);
            $this->assertSame(1, $r2['participated_sessions']);
            $this->assertSame(1, $r2['scan_activity_count']);
        });
    }

    public function test_snapshots_used_when_master_changes_and_incident_link(): void
    {
        $ctx = $this->makeContext();
        $s = $this->createSession($ctx, [
            'status' => PatrolSession::STATUS_COMPLETED,
            'points' => 1,
            'scans' => [['sec' => $ctx['security'], 'incident' => true, 'desc' => 'Rusak']],
        ]);
        $s['session']->update(['unit_name' => 'Snapshot Unit', 'post_name' => 'Snapshot Pos', 'team_name' => 'Snapshot Regu']);
        PatrolScan::query()->first()->update(['security_name' => 'Snapshot Satpam', 'patrol_point_name' => 'Snapshot Titik']);
        $ctx['unit']->update(['unit_name' => 'Master Baru']);
        $ctx['post']->update(['name' => 'Master Pos Baru']);
        $ctx['team']->update(['name' => 'Master Regu Baru']);

        $this->actingAs($ctx['admin'])
            ->get('/admin/patrol-report?unit_id='.$ctx['unit']->id)
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('by_unit.0.unit_name', 'Snapshot Unit')
                ->where('by_post.0.post_name', 'Snapshot Pos')
                ->where('by_post.0.unit_name', 'Snapshot Unit')
                ->where('by_team.0.team_name', 'Snapshot Regu')
                ->where('by_team.0.post_name', 'Snapshot Pos')
                ->has('incidents', 1)
                ->where('incidents.0.unit_name', 'Snapshot Unit')
                ->where('incidents.0.post_name', 'Snapshot Pos')
                ->where('incidents.0.team_name', 'Snapshot Regu')
                ->where('incidents.0.security_name', 'Snapshot Satpam')
                ->where('incidents.0.patrol_point_name', 'Snapshot Titik')
                ->where('incidents.0.detail_url', route('admin.patrol-session.show', $s['session']->id))
            );
    }

    public function test_export_follows_filter_scope_and_is_read_only(): void
    {
        $a = $this->makeContext(['label' => 'EA']);
        $b = $this->makeContext(['label' => 'EB']);
        $this->createSession($a, ['status' => PatrolSession::STATUS_COMPLETED, 'points' => 1, 'scans' => [['sec' => $a['security'], 'incident' => false]]]);
        $this->createSession($b, ['status' => PatrolSession::STATUS_COMPLETED, 'points' => 1, 'scans' => [['sec' => $b['security'], 'incident' => false]]]);

        $beforeSessions = PatrolSession::count();
        $beforeScans = PatrolScan::count();

        $super = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);
        $response = $this->actingAs($super)->get('/admin/patrol-report/export?unit_id='.$a['unit']->id);
        $response->assertOk();
        $this->assertStringContainsString('laporan-patroli-', (string) $response->headers->get('content-disposition'));

        $propsA = null;
        $this->actingAs($super)->get('/admin/patrol-report?unit_id='.$a['unit']->id)->assertOk()
            ->assertInertia(function ($p) use (&$propsA) {
                $p->where('summary.total_sessions', 1);
                $propsA = $p->toArray()['props'];
            });
        $exportA = new PatrolReportExport($propsA);
        $sheetsA = $exportA->sheets();
        $this->assertCount(6, $sheetsA);
        $this->assertSame(['Indikator', 'Nilai'], $sheetsA[0]->headings());
        $summaryLabels = array_map(fn ($r) => $r[0] ?? null, $sheetsA[0]->array());
        $this->assertContains('Total Titik Terpenuhi Unik', $summaryLabels);
        $this->assertContains('Total Aktivitas Scan', $summaryLabels);
        $unitRows = $sheetsA[1]->array();
        $this->assertCount(1, $unitRows);
        $this->assertSame($a['unit']->unit_name, $unitRows[0][0]);
        $this->assertNotSame($b['unit']->unit_name, $unitRows[0][0]);
        $this->assertSame('Ringkasan', $sheetsA[0]->title());
        $this->assertSame('Rekap Unit', $sheetsA[1]->title());
        $this->assertSame('Rekap Pos Jaga', $sheetsA[2]->title());
        $this->assertSame('Rekap Regu', $sheetsA[3]->title());
        $this->assertSame('Aktivitas Satpam', $sheetsA[4]->title());
        $this->assertSame('Laporan Insiden', $sheetsA[5]->title());

        $this->actingAs($a['admin'])->get('/admin/patrol-report/export?unit_id='.$b['unit']->id)->assertOk();
        $propsScoped = null;
        $this->actingAs($a['admin'])->get('/admin/patrol-report?unit_id='.$b['unit']->id)->assertOk()
            ->assertInertia(function ($p) use (&$propsScoped) {
                $p->where('summary.total_sessions', 1);
                $propsScoped = $p->toArray()['props'];
            });
        $scopedRows = (new PatrolReportExport($propsScoped))->sheets()[1]->array();
        $this->assertCount(1, $scopedRows);
        $this->assertSame($a['unit']->unit_name, $scopedRows[0][0]);

        $this->assertSame($beforeSessions, PatrolSession::count());
        $this->assertSame($beforeScans, PatrolScan::count());
    }

    private function makeContext(array $opts = []): array
    {
        $label = $opts['label'] ?? 'R'.Str::upper(Str::random(3));
        $unit = Unit::query()->create(['unit_name' => 'Unit '.$label, 'unit_code' => 'U'.Str::upper(Str::random(3)), 'unit_level' => 'uid']);
        $post = SecurityPost::query()->create(['unit_id' => $unit->id, 'name' => 'Post '.$label, 'post_code' => 'P'.Str::upper(Str::random(3))]);
        $team = SecurityTeam::query()->create(['security_post_id' => $post->id, 'name' => 'Team '.$label, 'code' => 'T'.Str::upper(Str::random(2)), 'is_active' => true]);
        $shift = Shift::query()->create(['unit_id' => $unit->id, 'name' => 'Pagi '.$label, 'code' => 'PG', 'type' => 'working', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'end_day_offset' => 0, 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'unit_id' => $unit->id]);
        $sec = $this->createSecurityUser('Budi '.$label, 'SEC-'.Str::upper(Str::random(4)));
        $pattern = SchedulePattern::query()->create(['unit_id' => $unit->id, 'name' => 'Pattern '.$label, 'cycle_length' => 1, 'is_active' => true]);
        $period = SchedulePeriod::withoutEvents(fn () => SchedulePeriod::query()->create(['unit_id' => $unit->id, 'security_post_id' => $post->id, 'security_team_id' => $team->id, 'name' => 'Period '.$label, 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'status' => 'active', 'created_by' => $admin->id]));

        return array_merge($sec, compact('admin', 'unit', 'post', 'team', 'shift', 'pattern', 'period'));
    }

    private function createSession(array $ctx, array $opts): array
    {
        $date = $opts['date'] ?? '2026-11-15';
        $status = $opts['status'] ?? PatrolSession::STATUS_PENDING;
        $pointCount = $opts['points'] ?? 1;
        $scans = $opts['scans'] ?? [];
        $extras = $opts['extra_members'] ?? [];

        $session = PatrolSession::query()->create([
            'schedule_period_id' => $ctx['period']->id,
            'security_team_id' => $ctx['team']->id,
            'security_post_id' => $ctx['post']->id,
            'unit_id' => $ctx['unit']->id,
            'shift_id' => $ctx['shift']->id,
            'work_date' => $date,
            'team_name' => $ctx['team']->name,
            'team_code' => $ctx['team']->code,
            'post_name' => $ctx['post']->name,
            'post_code' => $ctx['post']->post_code,
            'unit_name' => $ctx['unit']->unit_name,
            'unit_code' => $ctx['unit']->unit_code,
            'shift_name' => $ctx['shift']->name,
            'shift_code' => $ctx['shift']->code,
            'shift_type' => 'working',
            'shift_start_time' => '07:00:00',
            'shift_end_time' => '15:00:00',
            'shift_end_day_offset' => 0,
            'status' => $status,
        ]);

        $allSecs = array_values(array_unique(array_merge([$ctx['security']], $extras), SORT_REGULAR));
        foreach ($allSecs as $idx => $sec) {
            $pm = SchedulePeriodMember::query()->firstOrCreate(
                ['schedule_period_id' => $ctx['period']->id, 'security_id' => $sec->id],
                ['schedule_pattern_id' => $ctx['pattern']->id, 'start_offset' => $idx]
            );
            $schedule = Schedule::withoutEvents(fn () => Schedule::query()->create(['schedule_period_id' => $ctx['period']->id, 'schedule_period_member_id' => $pm->id, 'security_id' => $sec->id, 'shift_id' => $ctx['shift']->id, 'schedule_pattern_id' => $ctx['pattern']->id, 'pattern_day_order' => 1, 'date' => $date, 'source' => 'system_generated', 'created_by' => $ctx['admin']->id]));
            PatrolSessionMember::query()->create(['patrol_session_id' => $session->id, 'schedule_id' => $schedule->id, 'security_id' => $sec->id, 'security_name' => $sec->full_name, 'security_registration_number' => $sec->registration_number]);
        }

        $points = [];
        for ($p = 1; $p <= $pointCount; $p++) {
            $pp = PatrolPoint::query()->create(['security_post_id' => $ctx['post']->id, 'name' => 'Point '.$p.' '.Str::random(3), 'qr_code' => (string) Str::uuid()]);
            $points[] = PatrolSessionPoint::query()->create(['patrol_session_id' => $session->id, 'patrol_point_id' => $pp->id, 'patrol_point_name' => $pp->name, 'patrol_point_qr_code' => $pp->qr_code, 'sequence' => $p]);
        }

        $scanCount = is_int($scans) ? $scans : count($scans);
        for ($i = 0; $i < $scanCount && $i < count($points); $i++) {
            $scanDef = is_int($scans) ? ['sec' => $ctx['security'], 'incident' => false] : $scans[$i];
            $scanner = $scanDef['sec'];
            PatrolScan::query()->create([
                'patrol_session_id' => $session->id,
                'patrol_point_id' => $points[$i]->patrol_point_id,
                'security_id' => $scanner->id,
                'patrol_point_name' => $points[$i]->patrol_point_name,
                'patrol_point_qr_code' => $points[$i]->patrol_point_qr_code,
                'security_name' => $scanner->full_name,
                'security_registration_number' => $scanner->registration_number,
                'scanned_at' => Carbon::parse($date.' 09:0'.$i.':00'),
                'has_incident' => (bool) ($scanDef['incident'] ?? false),
                'incident_description' => $scanDef['desc'] ?? (($scanDef['incident'] ?? false) ? 'Insiden' : null),
                'idempotency_key' => 'test-'.$session->id.'-'.$i,
            ]);
        }

        return compact('session', 'points');
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
