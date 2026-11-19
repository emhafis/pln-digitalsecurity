<?php

namespace Tests\Feature;

use App\Models\PatrolPoint;
use App\Models\PatrolScan;
use App\Models\PatrolScanPhoto;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatrolFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_patrol_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(\Schema::hasTable('patrol_sessions'));
        $this->assertTrue(\Schema::hasTable('patrol_session_members'));
        $this->assertTrue(\Schema::hasTable('patrol_session_points'));
        $this->assertTrue(\Schema::hasTable('patrol_scans'));

        $this->assertTrue(\Schema::hasColumns('patrol_sessions', [
            'id', 'schedule_period_id', 'security_team_id', 'security_post_id', 'unit_id', 'shift_id',
            'work_date', 'team_name', 'team_code', 'post_name', 'post_code', 'unit_name', 'unit_code',
            'shift_name', 'shift_code', 'shift_type', 'shift_start_time', 'shift_end_time', 'shift_end_day_offset',
            'status', 'started_at', 'ended_at', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(\Schema::hasColumns('patrol_session_members', [
            'id', 'patrol_session_id', 'schedule_id', 'security_id', 'security_name', 'security_registration_number', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(\Schema::hasColumns('patrol_session_points', [
            'id', 'patrol_session_id', 'patrol_point_id', 'patrol_point_name', 'patrol_point_qr_code', 'sequence', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(\Schema::hasColumns('patrol_scans', [
            'id', 'patrol_session_id', 'patrol_point_id', 'security_id', 'patrol_point_name', 'patrol_point_qr_code',
            'security_name', 'security_registration_number', 'scanned_at', 'latitude', 'longitude', 'accuracy',
            'has_incident', 'incident_description', 'created_at', 'updated_at',
        ]));
    }

    public function test_session_unique_group_constraint(): void
    {
        [$ctx] = $this->makeSessionContext();

        $this->expectException(QueryException::class);
        PatrolSession::query()->create([
            'schedule_period_id' => $ctx['period']->id,
            'security_team_id' => $ctx['team']->id,
            'security_post_id' => $ctx['post']->id,
            'unit_id' => $ctx['unit']->id,
            'shift_id' => $ctx['shift']->id,
            'work_date' => '2026-11-15',
            'team_name' => 'Team A', 'team_code' => 'TA',
            'post_name' => 'Post A', 'post_code' => 'PA',
            'unit_name' => 'Unit A', 'unit_code' => 'UA',
            'shift_name' => 'Pagi', 'shift_code' => 'P', 'shift_type' => 'working',
            'shift_start_time' => '07:00:00', 'shift_end_time' => '15:00:00', 'shift_end_day_offset' => 0,
            'status' => PatrolSession::STATUS_PENDING,
        ]);
    }

    public function test_session_member_unique_session_schedule_constraint(): void
    {
        [$ctx, $session, $member] = $this->makeSessionContext(withMember: true);

        $this->expectException(QueryException::class);
        PatrolSessionMember::query()->create([
            'patrol_session_id' => $session->id,
            'schedule_id' => $member['schedule']->id,
            'security_id' => $member['security']->id,
            'security_name' => $member['security']->full_name,
            'security_registration_number' => $member['security']->registration_number,
        ]);
    }

    public function test_session_point_unique_session_point_constraint(): void
    {
        [$ctx, $session, $member, $point] = $this->makeSessionContext(withMember: true, withPoint: true);

        $this->expectException(QueryException::class);
        PatrolSessionPoint::query()->create([
            'patrol_session_id' => $session->id,
            'patrol_point_id' => $point['patrol_point']->id,
            'patrol_point_name' => $point['patrol_point']->name,
            'patrol_point_qr_code' => $point['patrol_point']->qr_code,
            'sequence' => 2,
        ]);
    }

    public function test_multi_scan_per_point_allowed_and_idempotency_uniqueness_still_enforced(): void
    {
        [$ctx, $session, $member, $point] = $this->makeSessionContext(withMember: true, withPoint: true);

        PatrolScan::query()->create([
            'patrol_session_id' => $session->id,
            'patrol_point_id' => $point['patrol_point']->id,
            'security_id' => $member['security']->id,
            'patrol_point_name' => $point['session_point']->patrol_point_name,
            'patrol_point_qr_code' => $point['session_point']->patrol_point_qr_code,
            'security_name' => $member['security']->full_name,
            'security_registration_number' => $member['security']->registration_number,
            'scanned_at' => now(),
            'has_incident' => false,
            'idempotency_key' => 'same-key',
        ]);

        // PATROL-14: titik yang sama boleh discan berkali-kali (record baru)
        PatrolScan::query()->create([
            'patrol_session_id' => $session->id,
            'patrol_point_id' => $point['patrol_point']->id,
            'security_id' => $member['security']->id,
            'patrol_point_name' => $point['session_point']->patrol_point_name,
            'patrol_point_qr_code' => $point['session_point']->patrol_point_qr_code,
            'security_name' => $member['security']->full_name,
            'security_registration_number' => $member['security']->registration_number,
            'scanned_at' => now()->addMinute(),
            'has_incident' => false,
            'idempotency_key' => 'other-key',
        ]);
        $this->assertSame(2, PatrolScan::query()->where('patrol_session_id', $session->id)->where('patrol_point_id', $point['patrol_point']->id)->count());

        // idempotency key duplikat pada sesi yang sama tetap ditolak
        $this->expectException(QueryException::class);
        PatrolScan::query()->create([
            'patrol_session_id' => $session->id,
            'patrol_point_id' => $point['patrol_point']->id,
            'security_id' => $member['security']->id,
            'patrol_point_name' => $point['session_point']->patrol_point_name,
            'patrol_point_qr_code' => $point['session_point']->patrol_point_qr_code,
            'security_name' => $member['security']->full_name,
            'security_registration_number' => $member['security']->registration_number,
            'scanned_at' => now()->addMinutes(2),
            'has_incident' => false,
            'idempotency_key' => 'same-key',
        ]);
    }

    public function test_relations_and_snapshot_storage(): void
    {
        [$ctx, $session, $member, $point] = $this->makeSessionContext(withMember: true, withPoint: true);

        $scan = PatrolScan::query()->create([
            'patrol_session_id' => $session->id,
            'patrol_point_id' => $point['patrol_point']->id,
            'security_id' => $member['security']->id,
            'patrol_point_name' => $point['session_point']->patrol_point_name,
            'patrol_point_qr_code' => $point['session_point']->patrol_point_qr_code,
            'security_name' => $member['security']->full_name,
            'security_registration_number' => $member['security']->registration_number,
            'scanned_at' => now(),
            'has_incident' => false,
        ]);

        $session->load(['members', 'points', 'scans']);
        $this->assertCount(1, $session->members);
        $this->assertCount(1, $session->points);
        $this->assertCount(1, $session->scans);

        $this->assertSame($member['security']->full_name, $session->members->first()->security_name);
        $this->assertSame($point['patrol_point']->name, $session->points->first()->patrol_point_name);
        $this->assertSame($point['patrol_point']->qr_code, $scan->patrol_point_qr_code);
        $this->assertSame($member['security']->full_name, $scan->security_name);
        $this->assertInstanceOf(PatrolScanPhoto::class, new PatrolScanPhoto);
    }

    public function test_session_status_defaults_to_pending(): void
    {
        [$ctx, $session] = $this->makeSessionContext();
        $this->assertSame(PatrolSession::STATUS_PENDING, $session->fresh()->status);
    }

    /**
     * @return array{0: array<string,mixed>, 1: PatrolSession, 2?: array<string,mixed>, 3?: array<string,mixed>}
     */
    private function makeSessionContext(bool $withMember = false, bool $withPoint = false): array
    {
        $user = User::factory()->create(['role' => 'security']);
        $security = Security::query()->create([
            'user_id' => $user->id,
            'registration_number' => 'SEC-001',
            'full_name' => 'Budi',
            'kta_issued_date' => '2024-01-01',
        ]);
        $unit = Unit::query()->create(['unit_name' => 'Unit A', 'unit_code' => 'UA', 'unit_level' => 'uid']);
        $post = SecurityPost::query()->create(['unit_id' => $unit->id, 'name' => 'Post A', 'post_code' => 'PA']);
        $team = SecurityTeam::query()->create(['security_post_id' => $post->id, 'name' => 'Team A', 'code' => 'TA', 'is_active' => true]);
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

        $session = PatrolSession::query()->create([
            'schedule_period_id' => $period->id,
            'security_team_id' => $team->id,
            'security_post_id' => $post->id,
            'unit_id' => $unit->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-11-15',
            'team_name' => 'Team A', 'team_code' => 'TA',
            'post_name' => 'Post A', 'post_code' => 'PA',
            'unit_name' => 'Unit A', 'unit_code' => 'UA',
            'shift_name' => 'Pagi', 'shift_code' => 'P', 'shift_type' => 'working',
            'shift_start_time' => '07:00:00', 'shift_end_time' => '15:00:00', 'shift_end_day_offset' => 0,
            'status' => PatrolSession::STATUS_PENDING,
        ]);

        $ctx = compact('user', 'security', 'unit', 'post', 'team', 'shift', 'period', 'pattern');
        $result = [$ctx, $session];

        $member = null;
        if ($withMember) {
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
                'date' => '2026-11-15',
                'source' => 'system_generated',
                'created_by' => $user->id,
            ]));
            $spm = PatrolSessionMember::query()->create([
                'patrol_session_id' => $session->id,
                'schedule_id' => $schedule->id,
                'security_id' => $security->id,
                'security_name' => $security->full_name,
                'security_registration_number' => $security->registration_number,
            ]);
            $member = compact('schedule', 'security', 'spm');
            $result[2] = $member;
        }

        if ($withPoint) {
            $patrolPoint = PatrolPoint::query()->create([
                'security_post_id' => $post->id,
                'name' => 'Gate 1',
                'qr_code' => (string) Str::uuid(),
            ]);
            $sessionPoint = PatrolSessionPoint::query()->create([
                'patrol_session_id' => $session->id,
                'patrol_point_id' => $patrolPoint->id,
                'patrol_point_name' => $patrolPoint->name,
                'patrol_point_qr_code' => $patrolPoint->qr_code,
                'sequence' => 1,
            ]);
            $result[3] = ['patrol_point' => $patrolPoint, 'session_point' => $sessionPoint];
        }

        return $result;
    }
}
