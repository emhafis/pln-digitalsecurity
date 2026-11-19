<?php

namespace Tests\Feature\Admin;

use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class SecurityTeamControllerTest extends SchedulingTestCase
{
    public function test_admin_can_view_teams_scoped_to_own_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $postA = $this->createPost($unitA);
        $postB = $this->createPost($unitB);
        $admin = $this->createAdmin($unitA);
        $teamA = $this->createTeam($postA, 'A');
        $this->createTeam($postB, 'B');

        $response = $this->actingAs($admin)->get('/admin/security-team');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SecurityTeam/Index')
            ->has('teams', 1)
            ->where('teams.0.code', $teamA->code)
            ->where('unit_id', $unitA->id)
        );
    }

    public function test_superadmin_can_view_teams_from_all_units(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $postA = $this->createPost($unitA);
        $postB = $this->createPost($unitB);
        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);
        $this->createTeam($postA, 'A');
        $this->createTeam($postB, 'B');

        $response = $this->actingAs($superadmin)->get('/admin/security-team');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SecurityTeam/Index')
            ->has('teams', 2)
            ->where('unit_id', '')
        );
    }

    public function test_sorting_by_unit_keeps_member_counts_and_reference_flag(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit);

        $team->assignMember($security, '2026-11-01');

        $response = $this->actingAs($admin)->get('/admin/security-team?sort=unit&dir=asc');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SecurityTeam/Index')
            ->has('teams', 1)
            ->where('teams.0.active_members_count', 1)
            ->where('teams.0.members_count', 1)
            ->where('teams.0.is_referenced', true)
        );
    }

    public function test_admin_can_create_team_in_scoped_unit(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/security-team', $this->teamPayload($post, [
            'code' => 'A',
            'name' => 'Regu A',
        ]));

        $team = SecurityTeam::query()->where('code', 'A')->firstOrFail();

        $response->assertRedirect(route('admin.security-team.show', $team->id));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('security_teams', [
            'id' => $team->id,
            'security_post_id' => $post->id,
            'code' => 'A',
            'name' => 'Regu A',
            'is_active' => true,
        ]);
    }

    public function test_store_regu_f_derives_code_f(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/security-team', $this->teamPayload($post, [
            'name' => 'Regu F',
            'code' => 'A',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('security_teams', [
            'security_post_id' => $post->id,
            'name' => 'Regu F',
            'code' => 'F',
        ]);
    }

    public function test_client_supplied_code_cannot_override_derived_code(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/security-team', $this->teamPayload($post, [
            'name' => 'Regu B',
            'code' => 'F',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('security_teams', [
            'security_post_id' => $post->id,
            'name' => 'Regu B',
            'code' => 'B',
        ]);
        $this->assertDatabaseMissing('security_teams', [
            'security_post_id' => $post->id,
            'code' => 'F',
        ]);
    }

    public function test_name_outside_allowed_set_is_rejected(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/security-team', $this->teamPayload($post, [
            'name' => 'Regu G',
            'code' => 'G',
        ]));

        $response->assertSessionHasErrors(['name', 'code']);
        $this->assertDatabaseCount('security_teams', 0);
    }

    public function test_admin_cannot_create_team_outside_unit_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $postB = $this->createPost($unitB);
        $admin = $this->createAdmin($unitA);

        $response = $this->actingAs($admin)->post('/admin/security-team', $this->teamPayload($postB));

        $response->assertSessionHasErrors('security_post_id');
        $this->assertDatabaseCount('security_teams', 0);
    }

    public function test_team_code_is_unique_per_post(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $this->createTeam($post, 'A');

        $response = $this->actingAs($admin)->post('/admin/security-team', $this->teamPayload($post, [
            'code' => 'B',
            'name' => 'Regu A',
        ]));

        $response->assertSessionHasErrors(['name', 'code']);
        $this->assertDatabaseCount('security_teams', 1);
    }

    public function test_inactive_team_still_occupies_its_slot(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post, 'A');
        $team->update(['is_active' => false]);

        $response = $this->actingAs($admin)->post('/admin/security-team', $this->teamPayload($post, [
            'name' => 'Regu A',
            'code' => 'A',
        ]));

        $response->assertSessionHasErrors(['name', 'code']);
        $this->assertDatabaseCount('security_teams', 1);
    }

    public function test_post_cannot_have_more_than_six_teams(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);

        foreach (array_keys(SecurityTeam::NAMES) as $slot) {
            $this->createTeam($post, $slot);
        }

        $response = $this->actingAs($admin)->post('/admin/security-team', $this->teamPayload($post, [
            'name' => 'Regu A',
            'code' => 'A',
        ]));

        $response->assertSessionHasErrors(['name', 'code']);
        $this->assertDatabaseCount('security_teams', 6);
    }

    public function test_same_team_code_is_allowed_on_a_different_post(): void
    {
        $unit = $this->createUnit();
        $postA = $this->createPost($unit);
        $postB = SecurityPost::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pos Kedua',
        ]);
        $admin = $this->createAdmin($unit);
        $this->createTeam($postA, 'A');

        $response = $this->actingAs($admin)->post('/admin/security-team', $this->teamPayload($postB, [
            'code' => 'B',
            'name' => 'Regu A',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('security_teams', 2);
        $this->assertDatabaseHas('security_teams', [
            'security_post_id' => $postB->id,
            'code' => 'A',
            'name' => 'Regu A',
        ]);
    }

    public function test_admin_can_update_team(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post, 'A');

        $response = $this->actingAs($admin)->put("/admin/security-team/{$team->id}", $this->teamPayload($post, [
            'code' => 'A',
            'name' => 'Regu B',
            'description' => 'Regu hasil perubahan',
            'is_active' => '0',
        ]));

        $response->assertRedirect(route('admin.security-team.show', $team->id));
        $response->assertSessionHasNoErrors();

        $team->refresh();

        $this->assertSame('B', $team->code);
        $this->assertSame('Regu B', $team->name);
        $this->assertSame('Regu hasil perubahan', $team->description);
        $this->assertFalse($team->is_active);
    }

    public function test_post_cannot_be_changed_when_team_has_membership_history(): void
    {
        $unit = $this->createUnit();
        $postA = $this->createPost($unit);
        $postB = SecurityPost::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pos Kedua',
        ]);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($postA, 'A');
        $security = $this->createSecurity($unit);

        $team->assignMember($security, '2026-11-01');

        $response = $this->actingAs($admin)->put("/admin/security-team/{$team->id}", $this->teamPayload($postB, [
            'code' => 'B',
            'name' => 'Regu A',
        ]));

        $response->assertSessionHasErrors('security_post_id');
        $this->assertSame($postA->id, $team->fresh()->security_post_id);
    }

    public function test_referenced_team_identity_cannot_be_changed(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post, 'A');
        $security = $this->createSecurity($unit);

        $team->assignMember($security, '2026-11-01');

        $response = $this->actingAs($admin)->put("/admin/security-team/{$team->id}", $this->teamPayload($post, [
            'name' => 'Regu B',
            'code' => 'B',
            'description' => 'Tidak boleh berubah namanya',
        ]));

        $response->assertSessionHasErrors('name');
        $this->assertSame('Regu A', $team->fresh()->name);
        $this->assertSame('A', $team->fresh()->code);
    }

    public function test_referenced_team_description_and_status_can_be_updated(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post, 'A');
        $security = $this->createSecurity($unit);

        $team->assignMember($security, '2026-11-01');

        $response = $this->actingAs($admin)->put("/admin/security-team/{$team->id}", $this->teamPayload($post, [
            'name' => 'Regu A',
            'code' => 'F',
            'description' => 'Deskripsi diperbarui',
            'is_active' => '0',
        ]));

        $response->assertSessionHasNoErrors();

        $team->refresh();

        $this->assertSame('Regu A', $team->name);
        $this->assertSame('A', $team->code);
        $this->assertSame('Deskripsi diperbarui', $team->description);
        $this->assertFalse($team->is_active);
    }

    public function test_updating_referenced_team_preserves_history_snapshot_period_and_schedules(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post, 'A');
        $security = $this->createSecurity($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');

        $membership = $team->assignMember($security, '2026-11-01');
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30');
        $periodMember = $period->addMember($security);
        $schedule = Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $periodMember->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put("/admin/security-team/{$team->id}", $this->teamPayload($post, [
            'name' => 'Regu A',
            'code' => 'A',
            'description' => 'Deskripsi diperbarui',
            'is_active' => '0',
        ]));

        $response->assertSessionHasNoErrors();

        $membership->refresh();
        $periodMember->refresh();
        $schedule->refresh();

        $this->assertSame('2026-11-01', $membership->joined_at->toDateString());
        $this->assertNull($membership->left_at);
        $this->assertSame($period->id, $periodMember->schedule_period_id);
        $this->assertSame($security->id, $periodMember->security_id);
        $this->assertSame($period->id, $schedule->schedule_period_id);
        $this->assertSame($periodMember->id, $schedule->schedule_period_member_id);
        $this->assertSame($security->id, $schedule->security_id);
        $this->assertSame($shift->id, $schedule->shift_id);
        $this->assertSame('2026-11-01', $schedule->date->toDateString());
        $this->assertDatabaseCount('schedule_period_members', 1);
        $this->assertDatabaseCount('schedules', 1);
        $this->assertDatabaseCount('schedule_periods', 1);
    }

    public function test_admin_can_toggle_team_status(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);

        $this->from("/admin/security-team/{$team->id}")
            ->actingAs($admin)
            ->patch("/admin/security-team/{$team->id}/toggle")
            ->assertRedirect("/admin/security-team/{$team->id}");

        $this->assertFalse($team->fresh()->is_active);

        $this->from("/admin/security-team/{$team->id}")
            ->actingAs($admin)
            ->patch("/admin/security-team/{$team->id}/toggle")
            ->assertRedirect("/admin/security-team/{$team->id}");

        $this->assertTrue($team->fresh()->is_active);
    }

    public function test_team_with_membership_history_cannot_be_deleted(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit);

        $team->assignMember($security, '2026-11-01');

        $response = $this->from('/admin/security-team')
            ->actingAs($admin)
            ->delete("/admin/security-team/{$team->id}");

        $response->assertRedirect('/admin/security-team');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('security_teams', ['id' => $team->id]);
    }

    public function test_unused_team_can_be_deleted(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);

        $response = $this->actingAs($admin)->delete("/admin/security-team/{$team->id}");

        $response->assertRedirect(route('admin.security-team.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('security_teams', ['id' => $team->id]);
    }

    public function test_admin_can_add_member_to_team(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit, 'Satpam A');

        $response = $this->actingAs($admin)->post("/admin/security-team/{$team->id}/member", [
            'security_id' => $security->id,
            'joined_at' => '2026-11-01',
        ]);

        $response->assertRedirect(route('admin.security-team.show', $team->id));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('security_team_members', [
            'security_team_id' => $team->id,
            'security_id' => $security->id,
            'joined_at' => '2026-11-01 00:00:00',
            'left_at' => null,
        ]);
        $this->assertSame(1, $team->activeMembers()->count());
    }

    public function test_admin_can_move_member_to_another_team_without_deleting_history(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $alpha = $this->createTeam($post, 'A');
        $bravo = $this->createTeam($post, 'B');
        $security = $this->createSecurity($unit, 'Satpam A');

        $alpha->assignMember($security, '2026-11-01');

        $response = $this->actingAs($admin)->post("/admin/security-team/{$alpha->id}/member", [
            'security_id' => $security->id,
            'joined_at' => '2026-12-01',
            'target_security_team_id' => $bravo->id,
        ]);

        $response->assertRedirect(route('admin.security-team.show', $alpha->id));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $oldMembership = $alpha->members()->where('security_id', $security->id)->firstOrFail();
        $newMembership = $bravo->members()->where('security_id', $security->id)->firstOrFail();

        $this->assertSame('2026-11-30', $oldMembership->left_at->toDateString());
        $this->assertNull($newMembership->left_at);
        $this->assertSame(2, $security->securityTeamMembers()->count());
        $this->assertSame(1, $security->securityTeamMembers()->whereNull('left_at')->count());
    }

    public function test_admin_can_end_membership(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit);

        $member = $team->assignMember($security, '2026-11-01');

        $response = $this->actingAs($admin)->put(
            "/admin/security-team/{$team->id}/member/{$member->id}/end",
            ['left_at' => '2026-11-15']
        );

        $response->assertRedirect(route('admin.security-team.show', $team->id));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertSame('2026-11-15', $member->fresh()->left_at->toDateString());
        $this->assertSame(0, $team->activeMembers()->count());
    }

    public function test_end_date_before_joined_date_is_rejected(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit);

        $member = $team->assignMember($security, '2026-11-10');

        $response = $this->actingAs($admin)->put(
            "/admin/security-team/{$team->id}/member/{$member->id}/end",
            ['left_at' => '2026-11-01']
        );

        $response->assertSessionHasErrors('left_at');
        $this->assertNull($member->fresh()->left_at);
    }

    public function test_already_ended_membership_cannot_be_ended_again(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit);

        $member = $team->assignMember($security, '2026-11-01', '2026-11-10');

        $response = $this->actingAs($admin)->put(
            "/admin/security-team/{$team->id}/member/{$member->id}/end",
            ['left_at' => '2026-11-15']
        );

        $response->assertSessionHasErrors('left_at');
        $this->assertSame('2026-11-10', $member->fresh()->left_at->toDateString());
    }

    public function test_inactive_team_cannot_receive_member(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);
        $team->update(['is_active' => false]);
        $security = $this->createSecurity($unit);

        $response = $this->actingAs($admin)->post("/admin/security-team/{$team->id}/member", [
            'security_id' => $security->id,
            'joined_at' => '2026-11-01',
        ]);

        $response->assertSessionHasErrors('security_id');
        $this->assertDatabaseCount('security_team_members', 0);
    }

    public function test_inactive_team_cannot_be_used_for_new_schedule_period(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);
        $team->update(['is_active' => false]);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', [
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'security_team_id' => $team->id,
            'name' => 'Periode Baru',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'members' => [],
        ]);

        $response->assertSessionHasErrors('security_team_id');
        $this->assertDatabaseCount('schedule_periods', 0);
    }

    public function test_existing_period_can_still_be_updated_after_its_team_is_deactivated(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30');

        $team->update(['is_active' => false]);

        $response = $this->actingAs($admin)->put("/admin/schedule-period/{$period->id}", [
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'security_team_id' => $team->id,
            'name' => 'Periode Diperbarui',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'members' => [],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('Periode Diperbarui', $period->fresh()->name);
    }

    public function test_moving_member_with_running_schedule_shows_warning_and_preserves_schedule(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $alpha = $this->createTeam($post, 'A');
        $bravo = $this->createTeam($post, 'B');
        $security = $this->createSecurity($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');

        $alpha->assignMember($security, '2026-11-01');
        $period = $this->createPeriod($unit, $post, $alpha, $admin, '2026-11-01', '2026-11-30');
        $periodMember = $period->addMember($security);
        $schedule = Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $periodMember->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post("/admin/security-team/{$alpha->id}/member", [
            'security_id' => $security->id,
            'joined_at' => '2026-12-01',
            'target_security_team_id' => $bravo->id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('warning');

        $schedule->refresh();

        $this->assertSame($period->id, $schedule->schedule_period_id);
        $this->assertSame($periodMember->id, $schedule->schedule_period_member_id);
        $this->assertSame($security->id, $schedule->security_id);
        $this->assertSame($shift->id, $schedule->shift_id);
        $this->assertSame('2026-11-01', $schedule->date->toDateString());
        $this->assertSame(
            [$security->id],
            $period->fresh()->members()->pluck('security_id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_ending_member_with_running_schedule_shows_warning_and_preserves_schedule(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $admin = $this->createAdmin($unit);
        $team = $this->createTeam($post);
        $security = $this->createSecurity($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');

        $member = $team->assignMember($security, '2026-11-01');
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30');
        $periodMember = $period->addMember($security);
        $schedule = Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $periodMember->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put(
            "/admin/security-team/{$team->id}/member/{$member->id}/end",
            ['left_at' => '2026-11-20']
        );

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('warning');
        $this->assertSame($shift->id, $schedule->fresh()->shift_id);
    }

    public function test_admin_cannot_access_team_outside_unit_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $postB = $this->createPost($unitB);
        $admin = $this->createAdmin($unitA);
        $teamB = $this->createTeam($postB);
        $securityB = $this->createSecurity($unitB);

        $this->actingAs($admin)->get("/admin/security-team/{$teamB->id}")->assertForbidden();
        $this->actingAs($admin)->get("/admin/security-team/{$teamB->id}/edit")->assertForbidden();
        $this->actingAs($admin)->put("/admin/security-team/{$teamB->id}", $this->teamPayload($postB))->assertForbidden();
        $this->actingAs($admin)->patch("/admin/security-team/{$teamB->id}/toggle")->assertForbidden();
        $this->actingAs($admin)->delete("/admin/security-team/{$teamB->id}")->assertForbidden();
        $this->actingAs($admin)->post("/admin/security-team/{$teamB->id}/member", [
            'security_id' => $securityB->id,
            'joined_at' => '2026-11-01',
        ])->assertForbidden();
    }

    public function test_security_role_cannot_access_team_management(): void
    {
        $unit = $this->createUnit();
        $securityUser = User::factory()->create([
            'role' => 'security',
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($securityUser)->get('/admin/security-team')->assertForbidden();
        $this->actingAs($securityUser)->get('/admin/security-team/create')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function teamPayload(SecurityPost $post, array $overrides = []): array
    {
        return array_merge([
            'security_post_id' => $post->id,
            'code' => 'A',
            'name' => 'Regu A',
            'description' => 'Regu untuk pengujian',
            'is_active' => '1',
        ], $overrides);
    }
}
