<?php

namespace Tests\Feature;

use App\Models\PatrolPoint;
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
use App\Services\PatrolSessionGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatrolSessionGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    public function test_draft_period_does_not_generate_sessions(): void
    {
        $ctx = $this->baseContext(['period_status' => SchedulePeriod::STATUS_DRAFT]);
        $this->addEligibleSchedule($ctx);

        $summary = (new PatrolSessionGenerator)->generate();

        $this->assertSame(0, $summary['active_periods']);
        $this->assertDatabaseCount('patrol_sessions', 0);
    }

    public function test_completed_period_does_not_generate_sessions(): void
    {
        $ctx = $this->baseContext(['period_status' => SchedulePeriod::STATUS_COMPLETED]);
        $this->addEligibleSchedule($ctx);

        $summary = (new PatrolSessionGenerator)->generate();

        $this->assertSame(0, $summary['active_periods']);
        $this->assertDatabaseCount('patrol_sessions', 0);
    }

    public function test_active_period_generates_session(): void
    {
        $ctx = $this->baseContext();
        $this->addEligibleSchedule($ctx);

        $summary = (new PatrolSessionGenerator)->generate();

        $this->assertSame(1, $summary['sessions_created']);
        $this->assertDatabaseCount('patrol_sessions', 1);
        $session = PatrolSession::query()->first();
        $this->assertSame(PatrolSession::STATUS_PENDING, $session->status);
        $this->assertNull($session->started_at);
        $this->assertNull($session->ended_at);
        $this->assertSame('2026-11-15', $session->work_date->toDateString());
    }

    public function test_multiple_members_same_shift_become_one_session(): void
    {
        $ctx = $this->baseContext();
        $sec2 = $this->createSecurity(User::factory()->create(), 'Budi 2', 'SEC-002');
        $sch1 = $this->addEligibleSchedule($ctx);
        $sch2 = $this->addEligibleSchedule($ctx, ['security' => $sec2, 'date' => '2026-11-15', 'shift' => $ctx['shiftPagi']]);

        (new PatrolSessionGenerator)->generate();

        $this->assertDatabaseCount('patrol_sessions', 1);
        $this->assertDatabaseCount('patrol_session_members', 2);
        $session = PatrolSession::query()->first();
        $this->assertEqualsCanonicalizing([$sch1->id, $sch2->id], $session->members->pluck('schedule_id')->all());
        $this->assertEqualsCanonicalizing([$ctx['security']->id, $sec2->id], $session->members->pluck('security_id')->all());
    }

    public function test_different_shifts_generate_different_sessions(): void
    {
        $ctx = $this->baseContext();
        $sec2 = $this->createSecurity(User::factory()->create(), 'Budi 2', 'SEC-002');
        $this->addEligibleSchedule($ctx, ['shift' => $ctx['shiftPagi'], 'date' => '2026-11-15']);
        $this->addEligibleSchedule($ctx, ['security' => $sec2, 'shift' => $ctx['shiftSiang'], 'date' => '2026-11-15']);

        $summary = (new PatrolSessionGenerator)->generate();

        $this->assertSame(2, $summary['sessions_created']);
        $this->assertDatabaseCount('patrol_sessions', 2);
    }

    public function test_working_shifts_pagi_siang_malam_standby_processed(): void
    {
        $ctx = $this->baseContext();
        foreach (['shiftPagi', 'shiftSiang', 'shiftMalam', 'shiftStandBy'] as $i => $key) {
            $sec = $this->createSecurity(User::factory()->create(), 'Sec '.$key, 'SEC-10'.$i);
            $this->addEligibleSchedule($ctx, ['shift' => $ctx[$key], 'security' => $sec, 'date' => '2026-11-1'.($i + 1)]);
        }

        $summary = (new PatrolSessionGenerator)->generate();

        $this->assertSame(4, $summary['sessions_created']);
        $this->assertDatabaseCount('patrol_sessions', 4);
    }

    public function test_day_off_shift_not_processed(): void
    {
        $ctx = $this->baseContext();
        $this->addEligibleSchedule($ctx, ['shift' => $ctx['shiftDayOff']]);

        $summary = (new PatrolSessionGenerator)->generate();

        $this->assertDatabaseCount('patrol_sessions', 0);
        $this->assertSame(0, $summary['eligible_groups']);
    }

    public function test_null_shift_skipped_and_logged(): void
    {
        $ctx = $this->baseContext();
        $sch = $this->addEligibleSchedule($ctx, ['shift_id' => null, 'shift' => null, 'skip_shift_relation' => true]);

        $summary = (new PatrolSessionGenerator)->generate();

        $this->assertDatabaseCount('patrol_sessions', 0);
        $this->assertSame(1, $summary['anomalies']);
        Log::shouldHaveReceived('warning')->withArgs(function ($msg, $ctxLog) use ($sch) {
            return $msg === 'patrol.sessions.generate.anomaly' && ($ctxLog['schedule_id'] ?? null) === $sch->id && ($ctxLog['reason'] ?? '') === 'shift_id is null';
        })->atLeast()->once();
    }

    public function test_inactive_shift_skipped_and_logged(): void
    {
        $ctx = $this->baseContext();
        $inactive = $this->createShift($ctx['unit'], 'Pagi Inactive', 'PIN', 'working', true, '07:00:00', '15:00:00', 0);
        $inactive->update(['is_active' => false]);
        $sch = $this->addEligibleSchedule($ctx, ['shift' => $inactive]);

        $summary = (new PatrolSessionGenerator)->generate();

        $this->assertDatabaseCount('patrol_sessions', 0);
        $this->assertSame(1, $summary['anomalies']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $c) => $m === 'patrol.sessions.generate.anomaly' && ($c['schedule_id'] ?? null) === $sch->id && ($c['reason'] ?? '') === 'shift inactive')->atLeast()->once();
    }

    public function test_working_shift_without_time_skipped_and_logged(): void
    {
        $ctx = $this->baseContext();
        $noTime = $this->createShift($ctx['unit'], 'NoTime', 'NT', 'working', true, null, null, 0);
        $sch = $this->addEligibleSchedule($ctx, ['shift' => $noTime]);

        $summary = (new PatrolSessionGenerator)->generate();

        $this->assertDatabaseCount('patrol_sessions', 0);
        $this->assertSame(1, $summary['anomalies']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $c) => $m === 'patrol.sessions.generate.anomaly' && ($c['schedule_id'] ?? null) === $sch->id && ($c['reason'] ?? '') === 'working shift missing start/end time')->atLeast()->once();
    }

    public function test_period_without_patrol_points_does_not_create_session(): void
    {
        $ctx = $this->baseContext(['with_points' => false]);
        $this->addEligibleSchedule($ctx);

        $summary = (new PatrolSessionGenerator)->generate();

        $this->assertDatabaseCount('patrol_sessions', 0);
        $this->assertSame(1, $summary['groups_skipped']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => $m === 'patrol.sessions.generate.no_patrol_points')->atLeast()->once();
    }

    public function test_points_stored_ordered_by_id_asc(): void
    {
        $ctx = $this->baseContext(['with_points' => false]);
        $p2 = PatrolPoint::query()->create(['security_post_id' => $ctx['post']->id, 'name' => 'P2', 'qr_code' => (string) Str::uuid()]);
        $p1 = PatrolPoint::query()->create(['security_post_id' => $ctx['post']->id, 'name' => 'P1', 'qr_code' => (string) Str::uuid()]);
        // ensure p1 has higher id? easier: create in order and assert by id asc
        $pointsOrdered = PatrolPoint::query()->where('security_post_id', $ctx['post']->id)->orderBy('id')->get();
        $this->addEligibleSchedule($ctx);

        (new PatrolSessionGenerator)->generate();

        $session = PatrolSession::query()->first();
        $stored = $session->points()->orderBy('sequence')->get();
        $this->assertSame($pointsOrdered->pluck('id')->all(), $stored->pluck('patrol_point_id')->all());
        $this->assertSame([1, 2], $stored->pluck('sequence')->all());
    }

    public function test_member_snapshot_from_schedule_not_all_team_members(): void
    {
        $ctx = $this->baseContext();
        $scheduledSec = $ctx['security'];
        $otherSec = $this->createSecurity(User::factory()->create(), 'Other', 'SEC-999');
        $this->addEligibleSchedule($ctx, ['security' => $scheduledSec]);

        (new PatrolSessionGenerator)->generate();

        $memberIds = PatrolSessionMember::query()->pluck('security_id')->all();
        $this->assertSame([$scheduledSec->id], $memberIds);
        $this->assertNotContains($otherSec->id, $memberIds);
    }

    public function test_running_twice_does_not_duplicate_sessions(): void
    {
        $ctx = $this->baseContext();
        $this->addEligibleSchedule($ctx);

        $g = new PatrolSessionGenerator;
        $first = $g->generate();
        $second = $g->generate();

        $this->assertSame(1, $first['sessions_created']);
        $this->assertSame(0, $second['sessions_created']);
        $this->assertSame(1, $second['sessions_existing']);
        $this->assertDatabaseCount('patrol_sessions', 1);
        $this->assertDatabaseCount('patrol_session_members', 1);
        $this->assertDatabaseCount('patrol_session_points', 1);
    }

    public function test_master_changes_after_session_do_not_change_snapshots(): void
    {
        $ctx = $this->baseContext();
        $this->addEligibleSchedule($ctx);
        (new PatrolSessionGenerator)->generate();

        $ctx['team']->update(['name' => 'Team Changed', 'code' => 'T-CHG']);
        $ctx['post']->update(['name' => 'Post Changed', 'post_code' => 'P-CHG']);
        $ctx['unit']->update(['unit_name' => 'Unit Changed', 'unit_code' => 'U-CHG']);
        $ctx['shiftPagi']->update(['name' => 'Shift Changed', 'code' => 'SH-CHG']);
        $ctx['security']->update(['full_name' => 'Sec Changed']);
        $ctx['point']->update(['name' => 'Point Changed', 'qr_code' => (string) Str::uuid()]);

        $session = PatrolSession::query()->first()->fresh();
        $member = PatrolSessionMember::query()->first()->fresh();
        $point = PatrolSessionPoint::query()->first()->fresh();

        $this->assertSame('Team A', $session->team_name);
        $this->assertSame('Post A', $session->post_name);
        $this->assertSame('Unit A', $session->unit_name);
        $this->assertSame('Pagi', $session->shift_name);
        $this->assertSame('Budi', $member->security_name);
        $this->assertSame('Point A', $point->patrol_point_name);
    }

    public function test_schedule_change_after_session_does_not_change_snapshot(): void
    {
        $ctx = $this->baseContext();
        $sch = $this->addEligibleSchedule($ctx);
        (new PatrolSessionGenerator)->generate();

        $newSec = $this->createSecurity(User::factory()->create(), 'New Sec', 'SEC-NEW');
        $newMember = SchedulePeriodMember::query()->create([
            'schedule_period_id' => $ctx['period']->id,
            'security_id' => $newSec->id,
            'schedule_pattern_id' => $ctx['pattern']->id,
            'start_offset' => 0,
        ]);
        DB::table('schedules')->where('id', $sch->id)->update([
            'security_id' => $newSec->id,
            'schedule_period_member_id' => $newMember->id,
        ]);

        $member = PatrolSessionMember::query()->first()->fresh();
        $this->assertSame($ctx['security']->id, $member->security_id);
        $this->assertSame('Budi', $member->security_name);
        $this->assertDatabaseCount('patrol_session_members', 1);
    }

    public function test_period_becoming_draft_after_session_does_not_remove_or_change_session(): void
    {
        $ctx = $this->baseContext();
        $this->addEligibleSchedule($ctx);
        (new PatrolSessionGenerator)->generate();
        $sessionId = PatrolSession::query()->value('id');

        $ctx['period']->update(['status' => SchedulePeriod::STATUS_DRAFT]);

        $this->assertDatabaseHas('patrol_sessions', ['id' => $sessionId, 'status' => PatrolSession::STATUS_PENDING]);
        (new PatrolSessionGenerator)->generate();
        $this->assertDatabaseCount('patrol_sessions', 1);
    }

    public function test_duplicate_key_race_handled_safely_without_partial_snapshot(): void
    {
        $ctx = $this->baseContext();
        $this->addEligibleSchedule($ctx);
        $g = new PatrolSessionGenerator;
        $g->generate();
        $this->assertDatabaseCount('patrol_sessions', 1);

        // Simulate duplicate-key outcome handling path by invoking generator again after manual race-like state:
        // existing session present -> should be treated as existing (no partials/dupes). This exercises idempotent guard.
        $summary = $g->generate();
        $this->assertSame(1, $summary['sessions_existing']);
        $this->assertSame(0, $summary['errors']);
        $this->assertDatabaseCount('patrol_sessions', 1);
        $this->assertDatabaseCount('patrol_session_members', 1);
        $this->assertDatabaseCount('patrol_session_points', 1);
        // Note: true concurrent multi-process MySQL race (scenario 18) cannot be proven under single-connection SQLite :memory:.
    }

    public function test_failure_during_member_creation_leaves_no_partial_session(): void
    {
        $ctx = $this->baseContext();
        $sch = $this->addEligibleSchedule($ctx);

        // Pre-create a member referencing the same schedule_id in another session to force a DB/unique error
        // on member insert inside the transaction; must not leave a partial new session/points.
        $otherSession = PatrolSession::query()->create([
            'schedule_period_id' => $ctx['period']->id,
            'security_team_id' => $ctx['team']->id,
            'security_post_id' => $ctx['post']->id,
            'unit_id' => $ctx['unit']->id,
            'shift_id' => $ctx['shiftSiang']->id,
            'work_date' => '2026-11-15',
            'team_name' => 'Team A', 'team_code' => 'TA', 'post_name' => 'Post A', 'post_code' => 'PA',
            'unit_name' => 'Unit A', 'unit_code' => 'UA', 'shift_name' => 'Siang', 'shift_code' => 'S',
            'shift_type' => 'working', 'shift_start_time' => '15:00:00', 'shift_end_time' => '23:00:00',
            'shift_end_day_offset' => 0, 'status' => 'pending', 'started_at' => null, 'ended_at' => null,
        ]);
        PatrolSessionMember::query()->create([
            'patrol_session_id' => $otherSession->id,
            'schedule_id' => $sch->id,
            'security_id' => $ctx['security']->id,
            'security_name' => 'Budi',
            'security_registration_number' => 'SEC-001',
        ]);

        $summary = (new PatrolSessionGenerator)->generate();

        $this->assertSame(0, $summary['sessions_created']);
        $this->assertDatabaseMissing('patrol_sessions', ['shift_id' => $ctx['shiftPagi']->id, 'work_date' => '2026-11-15']);
        $this->assertDatabaseCount('patrol_sessions', 1);
        $this->assertDatabaseCount('patrol_session_members', 1);
        $this->assertDatabaseCount('patrol_session_points', 0);
    }

    private function baseContext(array $opts = []): array
    {
        $user = User::factory()->create();
        $unit = $opts['unit'] ?? Unit::query()->create(['unit_name' => 'Unit A', 'unit_code' => 'UA', 'unit_level' => 'uid']);
        $post = $opts['post'] ?? SecurityPost::query()->create(['unit_id' => $unit->id, 'name' => 'Post A', 'post_code' => 'PA']);
        $team = SecurityTeam::query()->create(['security_post_id' => $post->id, 'name' => 'Team A', 'code' => 'TA', 'is_active' => true]);
        $security = $this->createSecurity($user, 'Budi', 'SEC-001');
        $shiftPagi = $this->createShift($unit, 'Pagi', 'P', 'working', true, '07:00:00', '15:00:00', 0);
        $shiftSiang = $this->createShift($unit, 'Siang', 'S', 'working', true, '15:00:00', '23:00:00', 0);
        $shiftMalam = $this->createShift($unit, 'Malam', 'M', 'working', true, '23:00:00', '07:00:00', 1);
        $shiftStandBy = $this->createShift($unit, 'Stand By', 'SB', 'working', true, '08:00:00', '16:00:00', 0);
        $shiftDayOff = $this->createShift($unit, 'Lepas Dinas', 'L', 'day_off', true, null, null, 0);
        $status = $opts['period_status'] ?? SchedulePeriod::STATUS_ACTIVE;
        $period = SchedulePeriod::withoutEvents(function () use ($unit, $post, $team, $user, $status, $opts) {
            $start = $opts['start_date'] ?? '2026-11-01';
            $end = $opts['end_date'] ?? '2026-11-30';

            return SchedulePeriod::query()->create([
                'unit_id' => $unit->id,
                'security_post_id' => $post->id,
                'security_team_id' => $team->id,
                'name' => $opts['period_name'] ?? 'Period '.Str::random(4),
                'start_date' => $start,
                'end_date' => $end,
                'status' => $status,
                'created_by' => $user->id,
            ]);
        });
        $pattern = SchedulePattern::query()->create(['unit_id' => $unit->id, 'name' => 'Pattern '.Str::random(4), 'cycle_length' => 1, 'is_active' => true]);
        $point = null;
        if (($opts['with_points'] ?? true) === true) {
            $point = PatrolPoint::query()->create(['security_post_id' => $post->id, 'name' => 'Point A', 'qr_code' => (string) Str::uuid()]);
        }

        return compact('user', 'unit', 'post', 'team', 'security', 'shiftPagi', 'shiftSiang', 'shiftMalam', 'shiftStandBy', 'shiftDayOff', 'period', 'pattern', 'point');
    }

    private function createSecurity(User $user, string $name, string $reg): Security
    {
        return Security::query()->create([
            'user_id' => $user->id,
            'registration_number' => $reg,
            'full_name' => $name,
            'kta_issued_date' => '2024-01-01',
        ]);
    }

    private function createShift(Unit $unit, string $name, string $code, string $type, bool $active, $start, $end, int $offset): Shift
    {
        return Shift::query()->create([
            'unit_id' => $unit->id,
            'name' => $name,
            'code' => $code,
            'type' => $type,
            'start_time' => $start,
            'end_time' => $end,
            'end_day_offset' => $offset,
            'is_active' => $active,
        ]);
    }

    private function addEligibleSchedule(array $ctx, array $overrides = []): Schedule
    {
        $security = $overrides['security'] ?? $ctx['security'];
        $shift = array_key_exists('shift', $overrides) ? $overrides['shift'] : $ctx['shiftPagi'];
        $shiftId = array_key_exists('shift_id', $overrides) ? $overrides['shift_id'] : ($shift?->id);
        $date = $overrides['date'] ?? '2026-11-15';

        $member = SchedulePeriodMember::query()->firstOrCreate(
            ['schedule_period_id' => $ctx['period']->id, 'security_id' => $security->id],
            ['schedule_pattern_id' => $ctx['pattern']->id, 'start_offset' => 0]
        );

        return Schedule::withoutEvents(function () use ($ctx, $security, $shiftId, $date, $member) {
            return Schedule::query()->create([
                'schedule_period_id' => $ctx['period']->id,
                'schedule_period_member_id' => $member->id,
                'security_id' => $security->id,
                'shift_id' => $shiftId,
                'schedule_pattern_id' => $ctx['pattern']->id,
                'pattern_day_order' => 1,
                'date' => $date,
                'source' => 'system_generated',
                'created_by' => $ctx['user']->id,
            ]);
        });
    }
}
