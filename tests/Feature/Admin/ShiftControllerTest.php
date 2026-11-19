<?php

namespace Tests\Feature\Admin;

use App\Models\Schedule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class ShiftControllerTest extends SchedulingTestCase
{
    public function test_admin_can_view_shift_list_scoped_to_own_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $admin = $this->createAdmin($unitA);
        $shiftA = $this->createShift($unitA, 'Pagi', 'PAGI');
        $this->createShift($unitB, 'Pagi B', 'PAGIB');

        $response = $this->actingAs($admin)->get('/admin/shift');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shift/Index')
            ->has('shifts', 1)
            ->where('shifts.0.code', $shiftA->code)
            ->where('unit_id', $unitA->id)
        );
    }

    public function test_superadmin_can_view_shifts_from_all_units(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);
        $this->createShift($unitA, 'Pagi', 'PAGI');
        $this->createShift($unitB, 'Siang', 'SIANG');

        $response = $this->actingAs($superadmin)->get('/admin/shift');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Shift/Index')
            ->has('shifts', 2)
            ->where('unit_id', '')
        );
    }

    public function test_admin_can_create_shift_in_scoped_unit(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/shift', [
            'unit_id' => $unit->id,
            'name' => 'Pagi',
            'code' => 'HACKED',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'end_day_offset' => '0',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.shift.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shifts', [
            'unit_id' => $unit->id,
            'name' => 'Pagi',
            'code' => 'P',
            'end_day_offset' => 0,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_create_shift_outside_unit_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $admin = $this->createAdmin($unitA);

        $response = $this->actingAs($admin)->post('/admin/shift', [
            'unit_id' => $unitB->id,
            'name' => 'Pagi',
            'code' => 'PAGI',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $response->assertSessionHasErrors('unit_id');
        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_shift_code_is_unique_per_unit(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $this->createShift($unit, 'Pagi', 'PAGI');

        $response = $this->actingAs($admin)->post('/admin/shift', [
            'unit_id' => $unit->id,
            'name' => 'Pagi Lain',
            'code' => 'PAGI',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseCount('shifts', 1);
    }

    public function test_same_shift_code_is_allowed_in_a_different_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);
        $this->createShift($unitA, 'Pagi', 'PAGI');

        $response = $this->actingAs($superadmin)->post('/admin/shift', [
            'unit_id' => $unitB->id,
            'name' => 'Pagi',
            'code' => 'PAGI',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $response->assertRedirect(route('admin.shift.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('shifts', 2);
    }

    public function test_cross_midnight_shift_is_accepted(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/shift', [
            'unit_id' => $unit->id,
            'name' => 'Malam',
            'code' => 'MALAM',
            'start_time' => '19:00',
            'end_time' => '07:00',
            'end_day_offset' => '1',
            'is_active' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shifts', [
            'code' => 'M',
            'start_time' => '19:00',
            'end_time' => '07:00',
            'end_day_offset' => 1,
        ]);
    }

    public function test_shift_with_invalid_same_day_time_range_is_rejected(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/shift', [
            'unit_id' => $unit->id,
            'name' => 'Pagi',
            'code' => 'PAGI',
            'start_time' => '16:00',
            'end_time' => '08:00',
            'end_day_offset' => '0',
        ]);

        $response->assertSessionHasErrors('end_time');
    }

    public function test_cross_midnight_shift_with_end_after_start_is_rejected(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/shift', [
            'unit_id' => $unit->id,
            'name' => 'Malam',
            'code' => 'MALAM',
            'start_time' => '19:00',
            'end_time' => '23:00',
            'end_day_offset' => '1',
        ]);

        $response->assertSessionHasErrors('end_time');
    }

    public function test_shift_requires_both_start_and_end_time(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);

        $response = $this->actingAs($admin)->post('/admin/shift', [
            'unit_id' => $unit->id,
            'name' => 'Pagi',
            'code' => 'PAGI',
            'start_time' => '08:00',
        ]);

        $response->assertSessionHasErrors('start_time');
    }

    public function test_admin_can_update_shift(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');

        $response = $this->actingAs($admin)->put("/admin/shift/{$shift->id}", [
            'unit_id' => $unit->id,
            'name' => 'Siang',
            'code' => 'HACKED',
            'start_time' => '07:00',
            'end_time' => '15:00',
            'end_day_offset' => '0',
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('admin.shift.show', $shift->id));
        $response->assertSessionHasNoErrors();

        $shift->refresh();

        $this->assertSame('Siang', $shift->name);
        $this->assertSame('S', $shift->code);
        $this->assertFalse($shift->is_active);
    }

    public function test_admin_cannot_show_or_update_shift_outside_unit_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $admin = $this->createAdmin($unitA);
        $shiftB = $this->createShift($unitB, 'Pagi B', 'PAGIB');

        $this->actingAs($admin)->get("/admin/shift/{$shiftB->id}")->assertForbidden();
        $this->actingAs($admin)->put("/admin/shift/{$shiftB->id}", [
            'unit_id' => $unitB->id,
            'name' => 'Pagi B',
            'code' => 'PAGIB',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ])->assertForbidden();
    }

    public function test_admin_can_toggle_shift_active_status(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');

        $this->from('/admin/shift')
            ->actingAs($admin)
            ->patch("/admin/shift/{$shift->id}/toggle")
            ->assertRedirect('/admin/shift');

        $this->assertFalse($shift->fresh()->is_active);

        $this->from('/admin/shift')
            ->actingAs($admin)
            ->patch("/admin/shift/{$shift->id}/toggle")
            ->assertRedirect('/admin/shift');

        $this->assertTrue($shift->fresh()->is_active);
    }

    public function test_security_role_cannot_access_shift_management(): void
    {
        $unit = $this->createUnit();
        $securityUser = User::factory()->create([
            'role' => 'security',
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($securityUser)->get('/admin/shift')->assertForbidden();
        $this->actingAs($securityUser)->get('/admin/shift/create')->assertForbidden();
    }

    public function test_shift_used_by_pattern_cannot_be_deleted(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');
        $pattern = $this->createPattern($unit);
        $pattern->addDetail($shift, 1);

        $response = $this->from('/admin/shift')
            ->actingAs($admin)
            ->delete("/admin/shift/{$shift->id}");

        $response->assertRedirect('/admin/shift');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    public function test_shift_used_by_schedule_cannot_be_deleted(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30');
        $member = $period->addMember($security);

        Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);

        $response = $this->from('/admin/shift')
            ->actingAs($admin)
            ->delete("/admin/shift/{$shift->id}");

        $response->assertRedirect('/admin/shift');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    public function test_unused_shift_can_be_deleted(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');

        $response = $this->actingAs($admin)->delete("/admin/shift/{$shift->id}");

        $response->assertRedirect(route('admin.shift.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_deactivating_shift_does_not_change_existing_schedule(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30');
        $member = $period->addMember($security);

        $schedule = Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);

        $this->from('/admin/shift')
            ->actingAs($admin)
            ->patch("/admin/shift/{$shift->id}/toggle")
            ->assertRedirect('/admin/shift');

        $this->assertFalse($shift->fresh()->is_active);
        $this->assertSame($shift->id, $schedule->fresh()->shift_id);
        $this->assertSame('2026-11-01', $schedule->fresh()->date->toDateString());
    }
}
