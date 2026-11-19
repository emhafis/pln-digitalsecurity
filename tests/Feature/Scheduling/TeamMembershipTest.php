<?php

namespace Tests\Feature\Scheduling;

use Illuminate\Validation\ValidationException;

class TeamMembershipTest extends SchedulingTestCase
{
    public function test_security_post_has_many_security_teams(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $alpha = $this->createTeam($post, 'A');
        $bravo = $this->createTeam($post, 'B');

        $this->assertTrue($post->fresh()->securityTeams->contains($alpha));
        $this->assertTrue($post->fresh()->securityTeams->contains($bravo));
    }

    public function test_team_has_many_members_and_active_scope(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit, 'Satpam A');

        $member = $team->assignMember($security, '2026-11-01');

        $this->assertTrue($member->securityTeam->is($team));
        $this->assertTrue($member->security->is($security));
        $this->assertTrue($team->activeMembers()->get()->contains($member));
        $this->assertTrue($security->activeSecurityTeamMembership->is($member));
    }

    public function test_assign_member_rejects_security_from_another_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $post = $this->createPost($unitA);
        $team = $this->createTeam($post);
        $outsider = $this->createSecurity($unitB, 'Satpam Unit B');

        $this->expectException(ValidationException::class);

        $team->assignMember($outsider, '2026-11-01');
    }

    public function test_assign_member_accepts_security_in_descendant_unit_scope(): void
    {
        $uid = $this->createUnit('uid');
        $ulp = $this->createUnit('ulp', $uid);
        $post = $this->createPost($uid);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($ulp, 'Satpam ULP');

        $member = $team->assignMember($security, '2026-11-01');

        $this->assertTrue($member->isActive());
    }

    public function test_moving_member_preserves_history_and_keeps_single_active_membership(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $alpha = $this->createTeam($post, 'A');
        $bravo = $this->createTeam($post, 'B');
        $security = $this->createSecurity($unit, 'Satpam A');

        $alpha->assignMember($security, '2026-11-01');
        $bravoMember = $bravo->assignMember($security, '2026-12-01');

        $alphaMember = $alpha->members()->where('security_id', $security->id)->firstOrFail();

        $this->assertTrue($alphaMember->left_at->isSameDay('2026-11-30'));
        $this->assertTrue($bravoMember->isActive());
        $this->assertSame(
            1,
            $security->securityTeamMembers()->whereNull('left_at')->count()
        );
        $this->assertSame(2, $security->securityTeamMembers()->count());

        $alpha->assignMember($security, '2027-01-01');

        $this->assertSame(
            1,
            $security->securityTeamMembers()->whereNull('left_at')->count()
        );
        $this->assertSame(3, $security->securityTeamMembers()->count());
        $this->assertTrue(
            $security->securityTeamMembers()->whereNull('left_at')->firstOrFail()->securityTeam->is($alpha)
        );
    }

    public function test_assign_member_rejects_left_at_before_joined_at(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit);

        $this->expectException(ValidationException::class);

        $team->assignMember($security, '2026-11-10', '2026-11-01');
    }
}
