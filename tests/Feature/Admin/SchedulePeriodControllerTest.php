<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceRecord;
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
use App\Models\SecurityTeamMember;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class SchedulePeriodControllerTest extends SchedulingTestCase
{
    public function test_admin_can_view_periods_scoped_to_own_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        [$postA, $teamA] = $this->makeTeamContext($unitA);
        [$postB, $teamB] = $this->makeTeamContext($unitB);
        $admin = $this->createAdmin($unitA);
        $periodA = $this->createPeriod($unitA, $postA, $teamA, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_ACTIVE, 'November A');
        $this->createPeriod($unitB, $postB, $teamB, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'November B');

        $response = $this->actingAs($admin)->get('/admin/schedule-period');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/Index')
            ->has('periods', 1)
            ->where('periods.0.name', $periodA->name)
            ->where('unit_id', $unitA->id)
        );
    }

    public function test_superadmin_can_view_periods_from_all_units(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        [$postA, $teamA] = $this->makeTeamContext($unitA);
        [$postB, $teamB] = $this->makeTeamContext($unitB);
        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);
        $this->createPeriod($unitA, $postA, $teamA, $superadmin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'A');
        $this->createPeriod($unitB, $postB, $teamB, $superadmin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'B');

        $response = $this->actingAs($superadmin)->get('/admin/schedule-period');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/Index')
            ->has('periods', 2)
            ->where('unit_id', '')
        );
    }

    public function test_index_filters_by_team_status_and_date_range(): void
    {
        $unit = $this->createUnit();
        [$post, $teamA] = $this->makeTeamContext($unit);
        $teamB = $this->createTeam($post, 'B');
        $admin = $this->createAdmin($unit);
        $this->createPeriod($unit, $post, $teamA, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'November Alpha');
        $this->createPeriod($unit, $post, $teamB, $admin, '2026-12-01', '2026-12-31', SchedulePeriod::STATUS_ACTIVE, 'Desember Bravo');

        $response = $this->actingAs($admin)->get(
            '/admin/schedule-period?security_team_id='.$teamA->id.'&status=draft&start_date=2026-11-01&end_date=2026-11-30'
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/Index')
            ->has('periods', 1)
            ->where('periods.0.name', 'November Alpha')
        );
    }

    public function test_admin_can_create_period_with_explicit_snapshot_members(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $securityA = $this->createSecurity($unit, 'Satpam A');
        $securityB = $this->createSecurity($unit, 'Satpam B');
        $pattern = SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pola 8 Hari',
            'cycle_length' => 8,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unit, $post, $team, [
            'name' => 'November 2026',
            'members' => [
                ['security_id' => $securityA->id, 'schedule_pattern_id' => $pattern->id, 'start_offset' => 2],
                ['security_id' => $securityB->id, 'schedule_pattern_id' => null, 'start_offset' => 0],
            ],
        ]));

        $period = SchedulePeriod::query()->where('name', 'November 2026')->firstOrFail();

        $response->assertRedirect(route('admin.schedule-period.show', $period->id));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedule_periods', [
            'id' => $period->id,
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'security_team_id' => $team->id,
            'created_by' => $admin->id,
            'status' => SchedulePeriod::STATUS_DRAFT,
        ]);
        $this->assertDatabaseCount('schedule_period_members', 2);
        $this->assertDatabaseHas('schedule_period_members', [
            'schedule_period_id' => $period->id,
            'security_id' => $securityA->id,
            'schedule_pattern_id' => $pattern->id,
            'start_offset' => 2,
        ]);
    }

    public function test_period_auto_snapshots_members_active_on_start_date(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $a = $this->createSecurity($unit, 'Satpam A');
        $b = $this->createSecurity($unit, 'Satpam B');
        $c = $this->createSecurity($unit, 'Satpam C');
        $d = $this->createSecurity($unit, 'Satpam D');
        $e = $this->createSecurity($unit, 'Satpam E');

        $this->addMembership($team, $a, '2026-10-01');
        $this->addMembership($team, $b, '2026-11-01');
        $this->addMembership($team, $c, '2026-10-01', '2026-10-31');
        $this->addMembership($team, $d, '2026-10-01', '2026-11-01');
        $this->addMembership($team, $e, '2026-11-02');

        $payload = $this->payload($unit, $post, $team);
        unset($payload['members']);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $payload);

        $response->assertSessionHasNoErrors();

        $period = SchedulePeriod::query()->where('name', 'Periode Uji')->firstOrFail();

        $this->assertSame(
            [$a->id, $b->id, $d->id],
            $period->members()->orderBy('security_id')->pluck('security_id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_snapshot_does_not_change_after_team_membership_changes(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $a = $this->createSecurity($unit, 'Satpam A');
        $b = $this->createSecurity($unit, 'Satpam B');

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unit, $post, $team, [
            'members' => [
                ['security_id' => $a->id, 'schedule_pattern_id' => null, 'start_offset' => 0],
            ],
        ]));
        $response->assertSessionHasNoErrors();

        $period = SchedulePeriod::query()->where('name', 'Periode Uji')->firstOrFail();

        SecurityTeamMember::create([
            'security_team_id' => $team->id,
            'security_id' => $a->id,
            'joined_at' => '2026-10-01',
            'left_at' => '2026-10-31',
        ]);
        SecurityTeamMember::create([
            'security_team_id' => $team->id,
            'security_id' => $b->id,
            'joined_at' => '2026-11-01',
        ]);

        $this->assertSame(
            [$a->id],
            $period->fresh()->members()->pluck('security_id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_copy_members_from_previous_period_preserves_pattern_and_offset(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $a = $this->createSecurity($unit, 'Satpam A');
        $b = $this->createSecurity($unit, 'Satpam B');
        $pattern = SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pola 8 Hari',
            'cycle_length' => 8,
            'is_active' => true,
        ]);
        $source = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_COMPLETED, 'November');
        $source->addMember($a, $pattern, 3);
        $source->addMember($b);

        $payload = $this->payload($unit, $post, $team, [
            'name' => 'Desember',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
            'copy_from_period_id' => $source->id,
        ]);
        unset($payload['members']);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $payload);

        $response->assertSessionHasNoErrors();

        $target = SchedulePeriod::query()->where('name', 'Desember')->firstOrFail();

        $this->assertSame(
            [$a->id, $b->id],
            $target->members()->orderBy('security_id')->pluck('security_id')->map(fn ($id) => (int) $id)->all()
        );
        $this->assertDatabaseHas('schedule_period_members', [
            'schedule_period_id' => $target->id,
            'security_id' => $a->id,
            'schedule_pattern_id' => $pattern->id,
            'start_offset' => 3,
        ]);
        $this->assertSame(
            [$a->id, $b->id],
            $source->fresh()->members()->orderBy('security_id')->pluck('security_id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_editing_new_period_members_does_not_change_source_period(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $a = $this->createSecurity($unit, 'Satpam A');
        $b = $this->createSecurity($unit, 'Satpam B');
        $source = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_COMPLETED, 'November');
        $source->addMember($a);
        $source->addMember($b);

        $payload = $this->payload($unit, $post, $team, [
            'name' => 'Desember',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
            'copy_from_period_id' => $source->id,
        ]);
        unset($payload['members']);

        $this->actingAs($admin)->post('/admin/schedule-period', $payload)->assertSessionHasNoErrors();

        $target = SchedulePeriod::query()->where('name', 'Desember')->firstOrFail();

        $response = $this->actingAs($admin)->put("/admin/schedule-period/{$target->id}", $this->payload($unit, $post, $team, [
            'name' => 'Desember',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
            'members' => [
                ['security_id' => $b->id, 'schedule_pattern_id' => null, 'start_offset' => 0],
            ],
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame([$b->id], $target->fresh()->members()->pluck('security_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(
            [$a->id, $b->id],
            $source->fresh()->members()->orderBy('security_id')->pluck('security_id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_period_with_schedules_rejects_locked_field_and_member_changes(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $a = $this->createSecurity($unit, 'Satpam A');
        $b = $this->createSecurity($unit, 'Satpam B');
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'November');
        $member = $period->addMember($a);
        $schedule = $this->createSchedule($period, $member, $a, $admin, $shift);

        $response = $this->actingAs($admin)->put("/admin/schedule-period/{$period->id}", $this->payload($unit, $post, $team, [
            'name' => 'November',
            'end_date' => '2026-12-15',
            'members' => [
                ['security_id' => $b->id, 'schedule_pattern_id' => null, 'start_offset' => 0],
            ],
        ]));

        $response->assertSessionHasErrors('end_date');
        $this->assertSame('2026-11-30', $period->fresh()->end_date->toDateString());
        $this->assertSame([$a->id], $period->fresh()->members()->pluck('security_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame($shift->id, $schedule->fresh()->shift_id);
    }

    public function test_period_with_schedules_allows_name_and_status_update(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit, 'Satpam A');
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'November');
        $member = $period->addMember($security);
        $schedule = $this->createSchedule($period, $member, $security, $admin, $shift);

        $response = $this->actingAs($admin)->put("/admin/schedule-period/{$period->id}", $this->payload($unit, $post, $team, [
            'name' => 'November Baru',
            'status' => SchedulePeriod::STATUS_ACTIVE,
            'members' => [
                ['security_id' => $security->id, 'schedule_pattern_id' => null, 'start_offset' => 0],
            ],
        ]));

        $response->assertSessionHasNoErrors();

        $period->refresh();

        $this->assertSame('November Baru', $period->name);
        $this->assertSame(SchedulePeriod::STATUS_ACTIVE, $period->status);
        $this->assertSame($shift->id, $schedule->fresh()->shift_id);
        $this->assertSame([$security->id], $period->members()->pluck('security_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_post_must_belong_to_selected_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        [$postA] = $this->makeTeamContext($unitA);
        [$postB, $teamB] = $this->makeTeamContext($unitB);
        $admin = $this->createAdmin($unitA);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unitA, $postB, $teamB, [
            'name' => 'Salah Unit',
        ]));

        $response->assertSessionHasErrors('security_post_id');
        $this->assertDatabaseCount('schedule_periods', 0);
    }

    public function test_team_must_belong_to_selected_post(): void
    {
        $unit = $this->createUnit();
        [$postA] = $this->makeTeamContext($unit);
        [$postB, $teamB] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unit, $postA, $teamB, [
            'name' => 'Salah Regu',
        ]));

        $response->assertSessionHasErrors('security_team_id');
        $this->assertDatabaseCount('schedule_periods', 0);
    }

    public function test_end_date_must_not_be_before_start_date(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unit, $post, $team, [
            'name' => 'Terbalik',
            'start_date' => '2026-11-30',
            'end_date' => '2026-11-01',
        ]));

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseCount('schedule_periods', 0);
    }

    public function test_overlapping_period_for_same_team_is_rejected(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'November');

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unit, $post, $team, [
            'name' => 'Overlap',
            'start_date' => '2026-11-15',
            'end_date' => '2026-12-15',
        ]));

        $response->assertSessionHasErrors('start_date');
        $this->assertDatabaseCount('schedule_periods', 1);
    }

    public function test_pattern_must_belong_to_period_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unitA);
        $admin = $this->createAdmin($unitA);
        $security = $this->createSecurity($unitA, 'Satpam A');
        $foreignPattern = SchedulePattern::factory()->create([
            'unit_id' => $unitB->id,
            'name' => 'Pola Unit B',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unitA, $post, $team, [
            'members' => [
                ['security_id' => $security->id, 'schedule_pattern_id' => $foreignPattern->id, 'start_offset' => 0],
            ],
        ]));

        $response->assertSessionHasErrors('members.0.schedule_pattern_id');
        $this->assertDatabaseCount('schedule_periods', 0);
    }

    public function test_inactive_pattern_cannot_be_used(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit, 'Satpam A');
        $pattern = SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pola Nonaktif',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unit, $post, $team, [
            'members' => [
                ['security_id' => $security->id, 'schedule_pattern_id' => $pattern->id, 'start_offset' => 0],
            ],
        ]));

        $response->assertSessionHasErrors('members.0.schedule_pattern_id');
        $this->assertDatabaseCount('schedule_periods', 0);
    }

    public function test_start_offset_must_be_inside_pattern_cycle(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit, 'Satpam A');
        $pattern = SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pola 8 Hari',
            'cycle_length' => 8,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unit, $post, $team, [
            'members' => [
                ['security_id' => $security->id, 'schedule_pattern_id' => $pattern->id, 'start_offset' => 8],
            ],
        ]));

        $response->assertSessionHasErrors('members.0.start_offset');
        $this->assertDatabaseCount('schedule_periods', 0);
    }

    public function test_start_offset_must_be_non_negative(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit, 'Satpam A');

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unit, $post, $team, [
            'members' => [
                ['security_id' => $security->id, 'schedule_pattern_id' => null, 'start_offset' => -1],
            ],
        ]));

        $response->assertSessionHasErrors('members.0.start_offset');
        $this->assertDatabaseCount('schedule_periods', 0);
    }

    public function test_duplicate_member_security_is_rejected(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit, 'Satpam A');

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unit, $post, $team, [
            'members' => [
                ['security_id' => $security->id, 'schedule_pattern_id' => null, 'start_offset' => 0],
                ['security_id' => $security->id, 'schedule_pattern_id' => null, 'start_offset' => 0],
            ],
        ]));

        $response->assertSessionHasErrors('members.1.security_id');
        $this->assertDatabaseCount('schedule_periods', 0);
    }

    public function test_security_must_be_inside_team_post_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unitA);
        $admin = $this->createAdmin($unitA);
        $foreignSecurity = $this->createSecurity($unitB, 'Satpam Unit B');

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unitA, $post, $team, [
            'members' => [
                ['security_id' => $foreignSecurity->id, 'schedule_pattern_id' => null, 'start_offset' => 0],
            ],
        ]));

        $response->assertSessionHasErrors('members.0.security_id');
        $this->assertDatabaseCount('schedule_periods', 0);
    }

    public function test_security_role_cannot_access_period_management(): void
    {
        $unit = $this->createUnit();
        $securityUser = User::factory()->create([
            'role' => 'security',
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($securityUser)->get('/admin/schedule-period')->assertForbidden();
        $this->actingAs($securityUser)->get('/admin/schedule-period/create')->assertForbidden();
    }

    public function test_admin_cannot_access_or_change_period_outside_unit_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        [$postA, $teamA] = $this->makeTeamContext($unitA);
        [$postB, $teamB] = $this->makeTeamContext($unitB);
        $admin = $this->createAdmin($unitA);
        $periodB = $this->createPeriod($unitB, $postB, $teamB, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'B');

        $this->actingAs($admin)->get("/admin/schedule-period/{$periodB->id}")->assertForbidden();
        $this->actingAs($admin)->get("/admin/schedule-period/{$periodB->id}/edit")->assertForbidden();
        $this->actingAs($admin)->put("/admin/schedule-period/{$periodB->id}", $this->payload($unitB, $postB, $teamB))->assertForbidden();
    }

    public function test_admin_cannot_create_period_for_unit_outside_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        [$postB, $teamB] = $this->makeTeamContext($unitB);
        $admin = $this->createAdmin($unitA);

        $response = $this->actingAs($admin)->post('/admin/schedule-period', $this->payload($unitB, $postB, $teamB));

        $response->assertSessionHasErrors('unit_id');
        $this->assertDatabaseCount('schedule_periods', 0);
    }

    public function test_admin_can_delete_ungenerated_period_with_valid_password(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'November');

        $response = $this->actingAs($admin)->delete("/admin/schedule-period/{$period->id}", [
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.schedule-period.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('schedule_periods', ['id' => $period->id]);
    }

    public function test_delete_removes_generated_rotation_manual_members_and_activities(): void
    {
        Storage::fake('public');

        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $securityA = $this->createSecurity($unit, 'Satpam A');
        $securityB = $this->createSecurity($unit, 'Satpam B');
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');

        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_ACTIVE, 'November');
        $memberA = $period->addMember($securityA);
        $memberB = $period->addMember($securityB);

        $rotation = $this->createSchedule($period, $memberA, $securityA, $admin, $shift, '2026-11-01', Schedule::SOURCE_SYSTEM_GENERATED);
        $manual = $this->createSchedule($period, $memberB, $securityB, $admin, $shift, '2026-11-02', Schedule::SOURCE_ADJUSTMENT);

        $record = AttendanceRecord::query()->create([
            'schedule_id' => $rotation->id,
            'schedule_period_id' => $period->id,
            'security_id' => $securityA->id,
            'security_post_id' => $post->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-11-01',
            'security_name' => $securityA->full_name,
            'security_registration_number' => $securityA->registration_number,
            'unit_id' => $unit->id,
            'unit_name' => $unit->unit_name,
            'security_team_id' => $team->id,
            'security_team_name' => $team->name,
            'security_post_name' => $post->name,
            'shift_name' => $shift->name,
            'shift_code' => $shift->code,
            'shift_type' => Shift::TYPE_WORKING,
            'shift_start_time' => '08:00:00',
            'shift_end_time' => '16:00:00',
            'shift_end_day_offset' => 0,
            'setting_gps_radius_meters' => 100,
            'setting_check_in_open_minutes' => 60,
            'setting_late_tolerance_minutes' => 10,
            'setting_early_checkout_tolerance_minutes' => 15,
            'setting_checkout_close_minutes' => 120,
            'setting_max_gps_accuracy_meters' => 100,
            'status' => AttendanceRecord::STATUS_SCHEDULED,
        ]);
        $record->corrections()->create([
            'requested_by' => $admin->id,
            'requested_by_role' => 'admin',
            'reason' => 'Test correction',
            'status' => 'pending',
        ]);

        $session = PatrolSession::query()->create([
            'schedule_period_id' => $period->id,
            'security_team_id' => $team->id,
            'security_post_id' => $post->id,
            'unit_id' => $unit->id,
            'shift_id' => $shift->id,
            'work_date' => '2026-11-01',
            'team_name' => $team->name,
            'team_code' => $team->code,
            'post_name' => $post->name,
            'post_code' => $post->post_code,
            'unit_name' => $unit->unit_name,
            'unit_code' => $unit->unit_code,
            'shift_name' => $shift->name,
            'shift_code' => $shift->code,
            'shift_type' => Shift::TYPE_WORKING,
            'shift_start_time' => '08:00:00',
            'shift_end_time' => '16:00:00',
            'shift_end_day_offset' => 0,
            'status' => PatrolSession::STATUS_PENDING,
        ]);
        PatrolSessionMember::query()->create([
            'patrol_session_id' => $session->id,
            'schedule_id' => $rotation->id,
            'security_id' => $securityA->id,
            'security_name' => $securityA->full_name,
            'security_registration_number' => $securityA->registration_number,
        ]);
        $patrolPoint = PatrolPoint::query()->create([
            'name' => 'Point A',
            'security_post_id' => $post->id,
        ]);
        $point = PatrolSessionPoint::query()->create([
            'patrol_session_id' => $session->id,
            'patrol_point_id' => $patrolPoint->id,
            'patrol_point_name' => $patrolPoint->name,
            'patrol_point_qr_code' => $patrolPoint->qr_code,
            'sequence' => 1,
        ]);
        $scan = PatrolScan::query()->create([
            'patrol_session_id' => $session->id,
            'patrol_point_id' => $patrolPoint->id,
            'security_id' => $securityA->id,
            'patrol_point_name' => $patrolPoint->name,
            'patrol_point_qr_code' => $patrolPoint->qr_code,
            'security_name' => $securityA->full_name,
            'security_registration_number' => $securityA->registration_number,
            'scanned_at' => now(),
            'has_incident' => true,
            'incident_description' => 'Test incident',
            'incident_photo_path' => 'patrol-incidents/legacy.jpg',
        ]);
        Storage::disk('public')->put('patrol-incidents/legacy.jpg', 'legacy');
        Storage::disk('public')->put('patrol-incidents/photo1.jpg', 'photo1');
        PatrolScanPhoto::query()->create([
            'patrol_scan_id' => $scan->id,
            'photo_path' => 'patrol-incidents/photo1.jpg',
            'sequence' => 1,
        ]);

        $otherUnit = $this->createUnit();
        [$otherPost, $otherTeam] = $this->makeTeamContext($otherUnit, 'Pos Lain');
        $otherPeriod = $this->createPeriod($otherUnit, $otherPost, $otherTeam, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'Other');
        $otherSecurity = $this->createSecurity($otherUnit, 'Other');
        $otherMember = $otherPeriod->addMember($otherSecurity);
        $this->createSchedule($otherPeriod, $otherMember, $otherSecurity, $admin, $this->createShift($otherUnit, 'Malam', 'MLM'), '2026-11-01', Schedule::SOURCE_SYSTEM_GENERATED);

        $response = $this->actingAs($admin)->delete("/admin/schedule-period/{$period->id}", [
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.schedule-period.index'));
        $this->assertDatabaseMissing('schedule_periods', ['id' => $period->id]);
        $this->assertDatabaseMissing('schedule_period_members', ['schedule_period_id' => $period->id]);
        $this->assertDatabaseMissing('schedules', ['schedule_period_id' => $period->id]);
        $this->assertDatabaseMissing('attendance_records', ['schedule_period_id' => $period->id]);
        $this->assertDatabaseMissing('attendance_corrections', ['attendance_record_id' => $record->id]);
        $this->assertDatabaseMissing('patrol_sessions', ['schedule_period_id' => $period->id]);
        $this->assertDatabaseMissing('patrol_session_members', ['patrol_session_id' => $session->id]);
        $this->assertDatabaseMissing('patrol_session_points', ['id' => $point->id]);
        $this->assertDatabaseMissing('patrol_scans', ['id' => $scan->id]);
        $this->assertDatabaseMissing('patrol_scan_photos', ['patrol_scan_id' => $scan->id]);
        Storage::disk('public')->assertMissing('patrol-incidents/legacy.jpg');
        Storage::disk('public')->assertMissing('patrol-incidents/photo1.jpg');

        $this->assertDatabaseHas('schedule_periods', ['id' => $otherPeriod->id]);
        $this->assertDatabaseCount('schedules', 1);
    }

    public function test_delete_rejects_wrong_password_empty_password_and_locked_period(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_ACTIVE, 'November');

        $this->actingAs($admin)->delete("/admin/schedule-period/{$period->id}", ['password' => 'wrong'])
            ->assertSessionHasErrors('password');
        $this->actingAs($admin)->delete("/admin/schedule-period/{$period->id}", ['password' => ''])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseHas('schedule_periods', ['id' => $period->id]);

        $period->update(['status' => SchedulePeriod::STATUS_COMPLETED]);
        $this->actingAs($admin)->delete("/admin/schedule-period/{$period->id}", ['password' => 'password'])
            ->assertSessionHasErrors('period');
        $this->assertDatabaseHas('schedule_periods', ['id' => $period->id]);
    }

    public function test_delete_respects_authorization_and_unit_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        [$postA, $teamA] = $this->makeTeamContext($unitA);
        [$postB, $teamB] = $this->makeTeamContext($unitB);
        $adminA = $this->createAdmin($unitA);
        $adminB = $this->createAdmin($unitB);
        $periodB = $this->createPeriod($unitB, $postB, $teamB, $adminB, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'B');

        $this->actingAs($adminA)->delete("/admin/schedule-period/{$periodB->id}", ['password' => 'password'])
            ->assertForbidden();
        $this->actingAs($adminA)->get("/admin/schedule-period/{$periodB->id}/delete-summary")
            ->assertForbidden();

        $this->assertDatabaseHas('schedule_periods', ['id' => $periodB->id]);
    }

    public function test_delete_summary_returns_actual_counts(): void
    {
        $unit = $this->createUnit();
        [$post, $team] = $this->makeTeamContext($unit);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit, 'Satpam A');
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30', SchedulePeriod::STATUS_DRAFT, 'November');
        $member = $period->addMember($security);
        $this->createSchedule($period, $member, $security, $admin, $shift, '2026-11-01', Schedule::SOURCE_SYSTEM_GENERATED);
        $this->createSchedule($period, $member, $security, $admin, $shift, '2026-11-02', Schedule::SOURCE_ADJUSTMENT);

        $response = $this->actingAs($admin)->getJson("/admin/schedule-period/{$period->id}/delete-summary");

        $response->assertOk();
        $response->assertJsonPath('period.id', $period->id);
        $response->assertJsonPath('is_locked', false);
        $response->assertJsonPath('can_delete', true);
        $response->assertJsonFragment(['key' => 'schedules_rotation', 'label' => 'Jadwal rotation', 'count' => 1]);
        $response->assertJsonFragment(['key' => 'schedules_manual', 'label' => 'Jadwal manual', 'count' => 1]);
        $response->assertJsonFragment(['key' => 'members', 'label' => 'Snapshot anggota regu', 'count' => 1]);
    }

    /**
     * @return array{0: SecurityPost, 1: SecurityTeam}
     */
    private function makeTeamContext(Unit $unit, string $postName = 'Pos Utama', string $slot = 'A'): array
    {
        $post = SecurityPost::factory()->create([
            'unit_id' => $unit->id,
            'name' => $postName,
        ]);
        $team = $this->createTeam($post, $slot);

        return [$post, $team];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Unit $unit, SecurityPost $post, SecurityTeam $team, array $overrides = []): array
    {
        return array_merge([
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'security_team_id' => $team->id,
            'name' => 'Periode Uji',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'status' => SchedulePeriod::STATUS_DRAFT,
            'members' => [],
        ], $overrides);
    }

    private function addMembership(
        SecurityTeam $team,
        Security $security,
        string $joinedAt,
        ?string $leftAt = null
    ): SecurityTeamMember {
        return SecurityTeamMember::create([
            'security_team_id' => $team->id,
            'security_id' => $security->id,
            'joined_at' => $joinedAt,
            'left_at' => $leftAt,
        ]);
    }

    private function createSchedule(
        SchedulePeriod $period,
        SchedulePeriodMember $member,
        Security $security,
        User $admin,
        Shift $shift,
        ?string $date = null,
        string $source = Schedule::SOURCE_ADJUSTMENT
    ): Schedule {
        return Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'date' => $date ?? $period->start_date->toDateString(),
            'source' => $source,
            'created_by' => $admin->id,
        ]);
    }
}
