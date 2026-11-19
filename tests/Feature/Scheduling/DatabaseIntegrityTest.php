<?php

namespace Tests\Feature\Scheduling;

use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\SchedulePeriodMember;
use App\Models\SecurityTeamMember;
use App\Models\Shift;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class DatabaseIntegrityTest extends SchedulingTestCase
{
    public function test_foreign_key_constraints_are_enforced(): void
    {
        $this->expectException(QueryException::class);

        Shift::create([
            'unit_id' => 999999,
            'name' => 'Pagi',
            'code' => 'PAGI',
        ]);
    }

    public function test_shift_code_is_unique_per_unit(): void
    {
        $unit = $this->createUnit();
        $this->createShift($unit, 'Pagi', 'PAGI');

        $this->expectException(QueryException::class);

        $this->createShift($unit, 'Pagi Lain', 'PAGI');
    }

    public function test_pattern_day_order_is_unique_per_pattern(): void
    {
        $unit = $this->createUnit();
        $pattern = $this->createPattern($unit);
        $pagi = $this->createShift($unit, 'Pagi', 'PAGI');
        $siang = $this->createShift($unit, 'Siang', 'SIANG');

        $pattern->addDetail($pagi, 1);

        $this->expectException(QueryException::class);

        $pattern->addDetail($siang, 1);
    }

    public function test_period_member_is_unique_per_period_and_security(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);

        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30');
        $period->addMember($security);

        $this->expectException(QueryException::class);

        SchedulePeriodMember::create([
            'schedule_period_id' => $period->id,
            'security_id' => $security->id,
        ]);
    }

    public function test_schedule_is_unique_per_security_and_date(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $teamA = $this->createTeam($post, 'A');
        $teamB = $this->createTeam($post, 'B');
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);

        $periodA = $this->createPeriod($unit, $post, $teamA, $admin, '2026-11-01', '2026-11-30');
        $periodB = $this->createPeriod($unit, $post, $teamB, $admin, '2026-11-01', '2026-11-30');

        $memberA = $periodA->addMember($security);
        $memberB = $periodB->addMember($security);

        Schedule::create([
            'schedule_period_id' => $periodA->id,
            'schedule_period_member_id' => $memberA->id,
            'security_id' => $security->id,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);

        $this->expectException(QueryException::class);

        Schedule::create([
            'schedule_period_id' => $periodB->id,
            'schedule_period_member_id' => $memberB->id,
            'security_id' => $security->id,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_ADJUSTMENT,
            'created_by' => $admin->id,
        ]);
    }

    public function test_one_active_membership_per_security_is_enforced_by_database(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit);

        SecurityTeamMember::create([
            'security_team_id' => $team->id,
            'security_id' => $security->id,
            'joined_at' => '2026-11-01',
            'left_at' => null,
        ]);

        $this->expectException(QueryException::class);

        SecurityTeamMember::create([
            'security_team_id' => $team->id,
            'security_id' => $security->id,
            'joined_at' => '2026-12-01',
            'left_at' => null,
        ]);
    }

    public function test_ended_memberships_do_not_block_new_active_membership(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit);

        SecurityTeamMember::create([
            'security_team_id' => $team->id,
            'security_id' => $security->id,
            'joined_at' => '2026-10-01',
            'left_at' => '2026-10-31',
        ]);

        $second = SecurityTeamMember::create([
            'security_team_id' => $team->id,
            'security_id' => $security->id,
            'joined_at' => '2026-11-01',
            'left_at' => null,
        ]);

        $this->assertTrue($second->exists);
    }

    public function test_security_team_member_is_unique_per_team_security_and_joined_date(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit);

        SecurityTeamMember::create([
            'security_team_id' => $team->id,
            'security_id' => $security->id,
            'joined_at' => '2026-11-01',
        ]);

        $this->expectException(QueryException::class);

        SecurityTeamMember::create([
            'security_team_id' => $team->id,
            'security_id' => $security->id,
            'joined_at' => '2026-11-01',
        ]);
    }

    public function test_period_overlap_is_rejected_for_same_team_for_all_statuses(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);

        $this->createPeriod(
            $unit,
            $post,
            $team,
            $admin,
            '2026-11-01',
            '2026-11-30',
            SchedulePeriod::STATUS_DRAFT
        );

        try {
            $this->createPeriod(
                $unit,
                $post,
                $team,
                $admin,
                '2026-11-15',
                '2026-12-15',
                SchedulePeriod::STATUS_ACTIVE
            );
            $this->fail('Overlapping active period was accepted.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);

        $this->createPeriod(
            $unit,
            $post,
            $team,
            $admin,
            '2026-10-01',
            '2026-11-01',
            SchedulePeriod::STATUS_COMPLETED
        );
    }

    public function test_period_can_start_the_day_after_previous_period_ends(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);

        $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30');

        $december = $this->createPeriod($unit, $post, $team, $admin, '2026-12-01', '2026-12-31');

        $this->assertTrue($december->exists);
    }

    public function test_period_overlap_is_allowed_for_a_different_team(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $teamA = $this->createTeam($post, 'A');
        $teamB = $this->createTeam($post, 'B');
        $admin = $this->createAdmin($unit);

        $this->createPeriod($unit, $post, $teamA, $admin, '2026-11-01', '2026-11-30');
        $periodB = $this->createPeriod($unit, $post, $teamB, $admin, '2026-11-01', '2026-11-30');

        $this->assertTrue($periodB->exists);
    }

    public function test_period_rejects_post_or_team_from_another_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $postA = $this->createPost($unitA);
        $postB = $this->createPost($unitB);
        $teamB = $this->createTeam($postB);
        $admin = $this->createAdmin($unitA);

        $this->expectException(ValidationException::class);

        $this->createPeriod($unitA, $postA, $teamB, $admin, '2026-11-01', '2026-11-30');
    }

    public function test_period_member_rejects_pattern_from_another_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $post = $this->createPost($unitA);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unitA);
        $security = $this->createSecurity($unitA);
        $foreignPattern = $this->createPattern($unitB);

        $period = $this->createPeriod($unitA, $post, $team, $admin, '2026-11-01', '2026-11-30');

        $this->expectException(ValidationException::class);

        $period->addMember($security, $foreignPattern);
    }

    public function test_schedule_rejects_shift_from_another_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $post = $this->createPost($unitA);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unitA);
        $security = $this->createSecurity($unitA);
        $foreignShift = $this->createShift($unitB, 'Pagi B', 'PAGIB');

        $period = $this->createPeriod($unitA, $post, $team, $admin, '2026-11-01', '2026-11-30');
        $member = $period->addMember($security);

        $this->expectException(ValidationException::class);

        Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'shift_id' => $foreignShift->id,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);
    }

    public function test_schedule_rejects_pattern_from_another_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $post = $this->createPost($unitA);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unitA);
        $security = $this->createSecurity($unitA);
        $foreignPattern = $this->createPattern($unitB);

        $period = $this->createPeriod($unitA, $post, $team, $admin, '2026-11-01', '2026-11-30');
        $member = $period->addMember($security);

        $this->expectException(ValidationException::class);

        Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'schedule_pattern_id' => $foreignPattern->id,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);
    }

    public function test_schedule_rejects_member_from_another_period(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $teamA = $this->createTeam($post, 'A');
        $teamB = $this->createTeam($post, 'B');
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);

        $periodA = $this->createPeriod($unit, $post, $teamA, $admin, '2026-11-01', '2026-11-30');
        $periodB = $this->createPeriod($unit, $post, $teamB, $admin, '2026-11-01', '2026-11-30');

        $memberA = $periodA->addMember($security);

        $this->expectException(ValidationException::class);

        Schedule::create([
            'schedule_period_id' => $periodB->id,
            'schedule_period_member_id' => $memberA->id,
            'security_id' => $security->id,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);
    }

    public function test_referenced_master_data_cannot_be_deleted(): void
    {
        $unit = $this->createUnit();
        $pattern = $this->createPattern($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');
        $pattern->addDetail($shift, 1);

        $this->expectException(QueryException::class);

        $shift->delete();
    }
}
