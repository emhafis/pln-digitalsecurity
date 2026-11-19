<?php

namespace Tests\Feature\Admin;

use App\Models\SecurityTeamMember;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class SchedulePeriodBulkCreatePageTest extends SchedulingTestCase
{
    public function test_bulk_create_page_renders_for_admin(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->get('/admin/schedule-period/bulk-create');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/BulkCreate')
            ->where('filters.unit_id', $unit->id)
        );
    }

    public function test_superadmin_without_selected_unit_receives_no_detail_data(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $this->createTeam($post);
        $this->createPattern($unit);
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->get('/admin/schedule-period/bulk-create');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/BulkCreate')
            ->has('units', 1)
            ->where('filters.unit_id', '')
            ->has('posts', 0)
            ->has('teams', 0)
            ->has('patterns', 0)
        );
    }

    public function test_superadmin_with_unit_receives_only_that_units_posts_and_patterns(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $postA = $this->createPost($unitA);
        $this->createPost($unitB);
        $this->createPattern($unitA, 'Pola Unit A');
        $this->createPattern($unitB, 'Pola Unit B');
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->get(
            '/admin/schedule-period/bulk-create?unit_id='.$unitA->id
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/BulkCreate')
            ->where('filters.unit_id', $unitA->id)
            ->has('posts', 1)
            ->where('posts.0.value', $postA->id)
            ->where('posts.0.unit_id', $unitA->id)
            ->has('patterns', 1)
            ->where('patterns.0.unit_id', $unitA->id)
            ->has('teams', 0)
        );
    }

    public function test_teams_are_filtered_to_selected_post_and_active_only(): void
    {
        $unit = $this->createUnit();
        $postA = $this->createPost($unit);
        $postB = $this->createPost($unit);
        $activeTeam = $this->createTeam($postA, 'A');
        $inactiveTeam = $this->createTeam($postA, 'B');
        $inactiveTeam->update(['is_active' => false]);
        $otherTeam = $this->createTeam($postB, 'A');
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)->get(
            '/admin/schedule-period/bulk-create?unit_id='.$unit->id.'&security_post_id='.$postA->id
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/BulkCreate')
            ->where('filters.security_post_id', $postA->id)
            ->has('teams', 1)
            ->where('teams.0.value', $activeTeam->id)
            ->where('teams.0.security_post_id', $postA->id)
            ->where('teams.0.is_active', true)
        );

        $this->assertNotSame($inactiveTeam->id, $activeTeam->id);
        $this->assertNotSame($otherTeam->id, $activeTeam->id);
    }

    public function test_admin_scope_overrides_foreign_unit_query(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $postA = $this->createPost($unitA);
        $postB = $this->createPost($unitB);
        $teamA = $this->createTeam($postA, 'A');
        $teamB = $this->createTeam($postB, 'A');
        $this->createPattern($unitA, 'Pola Unit A');
        $this->createPattern($unitB, 'Pola Unit B');
        $admin = $this->createAdmin($unitA);

        $response = $this->actingAs($admin)->get(
            '/admin/schedule-period/bulk-create?unit_id='.$unitB->id
            .'&security_post_id='.$postB->id
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/BulkCreate')
            ->where('filters.unit_id', $unitA->id)
            ->has('posts', 1)
            ->where('posts.0.unit_id', $unitA->id)
            ->has('patterns', 1)
            ->where('patterns.0.unit_id', $unitA->id)
            ->has('teams', 0)
        );

        $this->assertNotSame($teamA->id, $teamB->id);
    }

    public function test_team_memberships_payload_matches_page_requirements(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $activeSecurity = $this->createSecurity($unit, 'Satpam Aktif');
        $formerSecurity = $this->createSecurity($unit, 'Satpam Lama');
        $superadmin = $this->createSuperadmin();

        SecurityTeamMember::create([
            'security_team_id' => $team->id,
            'security_id' => $activeSecurity->id,
            'joined_at' => '2026-10-01',
            'left_at' => null,
        ]);
        SecurityTeamMember::create([
            'security_team_id' => $team->id,
            'security_id' => $formerSecurity->id,
            'joined_at' => '2026-01-01',
            'left_at' => '2026-10-15',
        ]);

        $response = $this->actingAs($superadmin)->get(
            '/admin/schedule-period/bulk-create?unit_id='.$unit->id.'&security_post_id='.$post->id
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/BulkCreate')
            ->has('teams', 1)
            ->has('teams.0.memberships', 2)
            ->where('teams.0.memberships', function ($memberships) use ($activeSecurity, $formerSecurity) {
                $bySecurity = collect($memberships)->keyBy('security_id');

                $active = $bySecurity->get($activeSecurity->id);
                $former = $bySecurity->get($formerSecurity->id);

                return $active !== null
                    && $former !== null
                    && $active['joined_at'] === '2026-10-01'
                    && $active['left_at'] === null
                    && $former['joined_at'] === '2026-01-01'
                    && $former['left_at'] === '2026-10-15';
            })
        );
    }

    private function createSuperadmin(): User
    {
        return User::factory()->create([
            'role' => 'superadmin',
            'unit_id' => null,
        ]);
    }
}
