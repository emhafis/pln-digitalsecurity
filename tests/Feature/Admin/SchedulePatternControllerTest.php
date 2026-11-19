<?php

namespace Tests\Feature\Admin;

use App\Models\Schedule;
use App\Models\SchedulePattern;
use App\Models\SchedulePatternDetail;
use App\Models\SchedulePeriod;
use App\Models\SchedulePeriodMember;
use App\Models\Security;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Feature\Scheduling\SchedulingTestCase;

class SchedulePatternControllerTest extends SchedulingTestCase
{
    public function test_admin_can_view_patterns_scoped_to_own_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $admin = $this->createAdmin($unitA);
        $patternA = SchedulePattern::factory()->create([
            'unit_id' => $unitA->id,
            'name' => 'Pola A',
        ]);
        SchedulePattern::factory()->create([
            'unit_id' => $unitB->id,
            'name' => 'Pola B',
        ]);

        $response = $this->actingAs($admin)->get('/admin/schedule-pattern');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePattern/Index')
            ->has('patterns', 1)
            ->where('patterns.0.name', $patternA->name)
            ->where('unit_id', $unitA->id)
        );
    }

    public function test_superadmin_can_view_patterns_from_all_units(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);
        SchedulePattern::factory()->create(['unit_id' => $unitA->id, 'name' => 'Pola A']);
        SchedulePattern::factory()->create(['unit_id' => $unitB->id, 'name' => 'Pola B']);

        $response = $this->actingAs($superadmin)->get('/admin/schedule-pattern');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePattern/Index')
            ->has('patterns', 2)
            ->where('unit_id', '')
        );
    }

    public function test_admin_can_filter_patterns_by_status(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pola Aktif',
            'is_active' => true,
        ]);
        SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pola Nonaktif',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->get('/admin/schedule-pattern?is_active=0');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePattern/Index')
            ->has('patterns', 1)
            ->where('patterns.0.name', 'Pola Nonaktif')
            ->where('is_active', '0')
        );
    }

    public function test_admin_can_create_pattern_with_ordered_details(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $pagi = $this->createShift($unit, 'Pagi', 'PAGI');
        $siang = $this->createShift($unit, 'Siang', 'SIANG');
        $malam = $this->createShift($unit, 'Malam', 'MALAM');

        $response = $this->actingAs($admin)->post('/admin/schedule-pattern', [
            'unit_id' => $unit->id,
            'name' => 'Pola 3 Hari',
            'description' => 'Pola uji tiga hari.',
            'cycle_length' => 3,
            'is_active' => '1',
            'details' => $this->detailsPayload([$pagi, $siang, $malam]),
        ]);

        $pattern = SchedulePattern::query()->where('name', 'Pola 3 Hari')->firstOrFail();

        $response->assertRedirect(route('admin.schedule-pattern.show', $pattern->id));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('schedule_patterns', [
            'id' => $pattern->id,
            'unit_id' => $unit->id,
            'cycle_length' => 3,
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('schedule_pattern_details', 3);
        $this->assertDatabaseHas('schedule_pattern_details', [
            'schedule_pattern_id' => $pattern->id,
            'day_order' => 1,
            'shift_id' => $pagi->id,
        ]);
        $this->assertDatabaseHas('schedule_pattern_details', [
            'schedule_pattern_id' => $pattern->id,
            'day_order' => 2,
            'shift_id' => $siang->id,
        ]);
        $this->assertDatabaseHas('schedule_pattern_details', [
            'schedule_pattern_id' => $pattern->id,
            'day_order' => 3,
            'shift_id' => $malam->id,
        ]);
    }

    public function test_pattern_name_is_unique_per_unit(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');
        SchedulePattern::factory()->create(['unit_id' => $unit->id, 'name' => 'Pola 8 Hari']);

        $response = $this->actingAs($admin)->post('/admin/schedule-pattern', [
            'unit_id' => $unit->id,
            'name' => 'Pola 8 Hari',
            'cycle_length' => 1,
            'details' => $this->detailsPayload([$shift]),
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('schedule_patterns', 1);
    }

    public function test_same_pattern_name_is_allowed_in_a_different_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);
        $shiftB = $this->createShift($unitB, 'Pagi', 'PAGI');
        SchedulePattern::factory()->create(['unit_id' => $unitA->id, 'name' => 'Pola 8 Hari']);

        $response = $this->actingAs($superadmin)->post('/admin/schedule-pattern', [
            'unit_id' => $unitB->id,
            'name' => 'Pola 8 Hari',
            'cycle_length' => 1,
            'details' => $this->detailsPayload([$shiftB]),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('schedule_patterns', 2);
    }

    public function test_detail_count_must_match_cycle_length(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');

        $response = $this->actingAs($admin)->post('/admin/schedule-pattern', [
            'unit_id' => $unit->id,
            'name' => 'Pola 3 Hari',
            'cycle_length' => 3,
            'details' => $this->detailsPayload([$shift, $shift]),
        ]);

        $response->assertSessionHasErrors('details');
        $this->assertDatabaseCount('schedule_patterns', 0);
        $this->assertDatabaseCount('schedule_pattern_details', 0);
    }

    public function test_duplicate_day_order_is_rejected(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');

        $response = $this->actingAs($admin)->post('/admin/schedule-pattern', [
            'unit_id' => $unit->id,
            'name' => 'Pola Duplikat',
            'cycle_length' => 3,
            'details' => [
                ['day_order' => 1, 'shift_id' => $shift->id],
                ['day_order' => 1, 'shift_id' => $shift->id],
                ['day_order' => 3, 'shift_id' => $shift->id],
            ],
        ]);

        $response->assertSessionHasErrors('details.1.day_order');
        $this->assertDatabaseCount('schedule_patterns', 0);
    }

    public function test_day_order_gap_is_rejected(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');

        $response = $this->actingAs($admin)->post('/admin/schedule-pattern', [
            'unit_id' => $unit->id,
            'name' => 'Pola Terputus',
            'cycle_length' => 3,
            'details' => [
                ['day_order' => 1, 'shift_id' => $shift->id],
                ['day_order' => 2, 'shift_id' => $shift->id],
                ['day_order' => 4, 'shift_id' => $shift->id],
            ],
        ]);

        $response->assertSessionHasErrors('details.2.day_order');
        $this->assertDatabaseCount('schedule_patterns', 0);
    }

    public function test_shift_must_belong_to_pattern_unit(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $admin = $this->createAdmin($unitA);
        $shiftB = $this->createShift($unitB, 'Pagi B', 'PAGIB');

        $response = $this->actingAs($admin)->post('/admin/schedule-pattern', [
            'unit_id' => $unitA->id,
            'name' => 'Pola Lintas Unit',
            'cycle_length' => 1,
            'details' => $this->detailsPayload([$shiftB]),
        ]);

        $response->assertSessionHasErrors('details.0.shift_id');
        $this->assertDatabaseCount('schedule_patterns', 0);
    }

    public function test_inactive_shift_cannot_be_used(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI', ['is_active' => false]);

        $response = $this->actingAs($admin)->post('/admin/schedule-pattern', [
            'unit_id' => $unit->id,
            'name' => 'Pola Shift Nonaktif',
            'cycle_length' => 1,
            'details' => $this->detailsPayload([$shift]),
        ]);

        $response->assertSessionHasErrors('details.0.shift_id');
        $this->assertDatabaseCount('schedule_patterns', 0);
    }

    public function test_admin_can_update_pattern_and_details(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $pagi = $this->createShift($unit, 'Pagi', 'PAGI');
        $siang = $this->createShift($unit, 'Siang', 'SIANG');
        $pattern = $this->createPatternWithDetails($unit, [$pagi, $siang], 'Pola Lama');

        $response = $this->actingAs($admin)->put("/admin/schedule-pattern/{$pattern->id}", [
            'unit_id' => $unit->id,
            'name' => 'Pola Baru',
            'description' => 'Deskripsi baru.',
            'cycle_length' => 2,
            'is_active' => '0',
            'details' => $this->detailsPayload([$siang, $pagi]),
        ]);

        $response->assertRedirect(route('admin.schedule-pattern.show', $pattern->id));
        $response->assertSessionHasNoErrors();

        $pattern->refresh()->load('details');

        $this->assertSame('Pola Baru', $pattern->name);
        $this->assertSame('Deskripsi baru.', $pattern->description);
        $this->assertFalse($pattern->is_active);
        $this->assertSame([$siang->id, $pagi->id], $pattern->details->pluck('shift_id')->all());
        $this->assertSame([1, 2], $pattern->details->pluck('day_order')->all());
    }

    public function test_cycle_length_cannot_change_when_pattern_is_used(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $pagi = $this->createShift($unit, 'Pagi', 'PAGI');
        $siang = $this->createShift($unit, 'Siang', 'SIANG');
        $malam = $this->createShift($unit, 'Malam', 'MALAM');
        $pattern = $this->createPatternWithDetails($unit, [$pagi, $siang], 'Pola Terpakai');
        $this->makePeriodContext($unit, $admin, $security, $pattern);

        $response = $this->actingAs($admin)->put("/admin/schedule-pattern/{$pattern->id}", [
            'unit_id' => $unit->id,
            'name' => $pattern->name,
            'cycle_length' => 3,
            'details' => $this->detailsPayload([$pagi, $siang, $malam]),
        ]);

        $response->assertSessionHasErrors('cycle_length');
        $this->assertSame(2, $pattern->fresh()->cycle_length);
        $this->assertSame(2, $pattern->fresh()->details()->count());
    }

    public function test_unit_cannot_change_when_pattern_is_used(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $admin = $this->createAdmin($unitA);
        $security = $this->createSecurity($unitA);
        $pagiA = $this->createShift($unitA, 'Pagi A', 'PAGIA');
        $siangA = $this->createShift($unitA, 'Siang A', 'SIANGA');
        $pagiB = $this->createShift($unitB, 'Pagi B', 'PAGIB');
        $siangB = $this->createShift($unitB, 'Siang B', 'SIANGB');
        $pattern = $this->createPatternWithDetails($unitA, [$pagiA, $siangA], 'Pola Terpakai');
        $this->makePeriodContext($unitA, $admin, $security, $pattern);

        $response = $this->actingAs($admin)->put("/admin/schedule-pattern/{$pattern->id}", [
            'unit_id' => $unitB->id,
            'name' => $pattern->name,
            'cycle_length' => 2,
            'details' => $this->detailsPayload([$pagiB, $siangB]),
        ]);

        $response->assertSessionHasErrors('unit_id');
        $this->assertSame($unitA->id, $pattern->fresh()->unit_id);
    }

    public function test_unused_pattern_can_be_deleted_with_its_details(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $pagi = $this->createShift($unit, 'Pagi', 'PAGI');
        $siang = $this->createShift($unit, 'Siang', 'SIANG');
        $pattern = $this->createPatternWithDetails($unit, [$pagi, $siang], 'Pola Hapus');

        $response = $this->actingAs($admin)->delete("/admin/schedule-pattern/{$pattern->id}");

        $response->assertRedirect(route('admin.schedule-pattern.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('schedule_patterns', ['id' => $pattern->id]);
        $this->assertDatabaseMissing('schedule_pattern_details', ['schedule_pattern_id' => $pattern->id]);
    }

    public function test_pattern_used_by_period_member_cannot_be_deleted(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $pagi = $this->createShift($unit, 'Pagi', 'PAGI');
        $pattern = $this->createPatternWithDetails($unit, [$pagi], 'Pola Periode');
        $this->makePeriodContext($unit, $admin, $security, $pattern);

        $response = $this->from('/admin/schedule-pattern')
            ->actingAs($admin)
            ->delete("/admin/schedule-pattern/{$pattern->id}");

        $response->assertRedirect('/admin/schedule-pattern');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('schedule_patterns', ['id' => $pattern->id]);
    }

    public function test_pattern_used_by_schedule_cannot_be_deleted(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $pagi = $this->createShift($unit, 'Pagi', 'PAGI');
        $pattern = $this->createPatternWithDetails($unit, [$pagi], 'Pola Jadwal');
        [$period, $member] = $this->makePeriodContext($unit, $admin, $security, $pattern);

        Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'shift_id' => $pagi->id,
            'schedule_pattern_id' => $pattern->id,
            'pattern_day_order' => 1,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);

        $response = $this->from('/admin/schedule-pattern')
            ->actingAs($admin)
            ->delete("/admin/schedule-pattern/{$pattern->id}");

        $response->assertRedirect('/admin/schedule-pattern');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('schedule_patterns', ['id' => $pattern->id]);
    }

    public function test_updating_pattern_details_does_not_change_existing_schedule(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $pagi = $this->createShift($unit, 'Pagi', 'PAGI');
        $siang = $this->createShift($unit, 'Siang', 'SIANG');
        $pattern = $this->createPatternWithDetails($unit, [$pagi], 'Pola Histori');
        [$period, $member] = $this->makePeriodContext($unit, $admin, $security, $pattern);

        $schedule = Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'shift_id' => $pagi->id,
            'schedule_pattern_id' => $pattern->id,
            'pattern_day_order' => 1,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put("/admin/schedule-pattern/{$pattern->id}", [
            'unit_id' => $unit->id,
            'name' => $pattern->name,
            'cycle_length' => 1,
            'details' => $this->detailsPayload([$siang]),
        ]);

        $response->assertSessionHasNoErrors();

        $schedule->refresh();

        $this->assertSame($pagi->id, $schedule->shift_id);
        $this->assertSame(1, $schedule->pattern_day_order);
        $this->assertSame($pattern->id, $schedule->schedule_pattern_id);
        $this->assertSame($siang->id, $pattern->fresh()->details()->first()->shift_id);
    }

    public function test_detail_save_failure_rolls_back_pattern_creation(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $pagi = $this->createShift($unit, 'Pagi', 'PAGI');
        $siang = $this->createShift($unit, 'Siang', 'SIANG');
        $shouldFail = true;

        SchedulePatternDetail::saving(function (SchedulePatternDetail $detail) use (&$shouldFail) {
            if ($shouldFail && (int) $detail->day_order === 2) {
                $shouldFail = false;

                throw new RuntimeException('forced detail save failure');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->post('/admin/schedule-pattern', [
                'unit_id' => $unit->id,
                'name' => 'Pola Rollback',
                'cycle_length' => 2,
                'details' => $this->detailsPayload([$pagi, $siang]),
            ]);

            $this->fail('Expected detail save failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced detail save failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('schedule_patterns', 0);
        $this->assertDatabaseCount('schedule_pattern_details', 0);
    }

    public function test_admin_can_toggle_pattern_status(): void
    {
        $unit = $this->createUnit();
        $admin = $this->createAdmin($unit);
        $pattern = SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pola Toggle',
            'is_active' => true,
        ]);

        $this->from('/admin/schedule-pattern')
            ->actingAs($admin)
            ->patch("/admin/schedule-pattern/{$pattern->id}/toggle")
            ->assertRedirect('/admin/schedule-pattern');

        $this->assertFalse($pattern->fresh()->is_active);

        $this->from('/admin/schedule-pattern')
            ->actingAs($admin)
            ->patch("/admin/schedule-pattern/{$pattern->id}/toggle")
            ->assertRedirect('/admin/schedule-pattern');

        $this->assertTrue($pattern->fresh()->is_active);
    }

    public function test_security_role_cannot_access_pattern_management(): void
    {
        $unit = $this->createUnit();
        $securityUser = User::factory()->create([
            'role' => 'security',
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($securityUser)->get('/admin/schedule-pattern')->assertForbidden();
        $this->actingAs($securityUser)->get('/admin/schedule-pattern/create')->assertForbidden();
    }

    public function test_admin_cannot_access_or_change_pattern_outside_unit_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $admin = $this->createAdmin($unitA);
        $shiftB = $this->createShift($unitB, 'Pagi B', 'PAGIB');
        $patternB = $this->createPatternWithDetails($unitB, [$shiftB], 'Pola B');

        $this->actingAs($admin)->get("/admin/schedule-pattern/{$patternB->id}")->assertForbidden();
        $this->actingAs($admin)->get("/admin/schedule-pattern/{$patternB->id}/edit")->assertForbidden();
        $this->actingAs($admin)->put("/admin/schedule-pattern/{$patternB->id}", [
            'unit_id' => $unitB->id,
            'name' => $patternB->name,
            'cycle_length' => 1,
            'details' => $this->detailsPayload([$shiftB]),
        ])->assertForbidden();
        $this->actingAs($admin)->delete("/admin/schedule-pattern/{$patternB->id}")->assertForbidden();
    }

    public function test_admin_cannot_create_pattern_for_unit_outside_scope(): void
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $admin = $this->createAdmin($unitA);
        $shiftB = $this->createShift($unitB, 'Pagi B', 'PAGIB');

        $response = $this->actingAs($admin)->post('/admin/schedule-pattern', [
            'unit_id' => $unitB->id,
            'name' => 'Pola Luar Scope',
            'cycle_length' => 1,
            'details' => $this->detailsPayload([$shiftB]),
        ]);

        $response->assertSessionHasErrors('unit_id');
        $this->assertDatabaseCount('schedule_patterns', 0);
    }

    public function test_inactive_pattern_cannot_be_assigned_to_a_new_period_member(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $pattern = SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pola Nonaktif',
            'is_active' => false,
        ]);
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30');

        $this->expectException(ValidationException::class);

        $period->addMember($security, $pattern);
    }

    /**
     * @param  array<int, Shift>  $shifts
     * @return array<int, array{day_order: int, shift_id: int}>
     */
    private function detailsPayload(array $shifts): array
    {
        $details = [];

        foreach (array_values($shifts) as $index => $shift) {
            $details[] = [
                'day_order' => $index + 1,
                'shift_id' => $shift->id,
            ];
        }

        return $details;
    }

    /**
     * @param  array<int, Shift>  $shifts
     */
    private function createPatternWithDetails(Unit $unit, array $shifts, string $name): SchedulePattern
    {
        $pattern = SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => $name,
            'cycle_length' => count($shifts),
        ]);

        foreach (array_values($shifts) as $index => $shift) {
            $pattern->addDetail($shift, $index + 1);
        }

        return $pattern;
    }

    /**
     * @return array{0: SchedulePeriod, 1: SchedulePeriodMember}
     */
    private function makePeriodContext(
        Unit $unit,
        User $admin,
        Security $security,
        ?SchedulePattern $pattern = null
    ): array {
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30');
        $member = $period->addMember($security, $pattern);

        return [$period, $member];
    }
}
