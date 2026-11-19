<?php

namespace Tests\Feature\Admin;

use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\Security;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Scheduling\SchedulingTestCase;

class ScheduleCalendarControllerTest extends SchedulingTestCase
{
    use RefreshDatabase;

    public function test_calendar_only_returns_schedules_within_selected_month(): void
    {
        $context = $this->prepareContext();

        $this->makeSchedule($context, '2026-11-10', $context['pagi']);
        $this->makeSchedule($context, '2026-12-01', $context['pagi']);
        $this->makeSchedule($context, '2026-10-31', $context['pagi']);

        $response = $this->actingAs($context['admin'])->get('/admin/schedule-calendar?month=2026-11');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/ScheduleCalendar/Index')
            ->where('month', '2026-11')
            ->where('month_label', 'November 2026')
            ->where('prev_month', '2026-10')
            ->where('next_month', '2026-12')
            ->has('rows', 1)
            ->where('rows.0.date', '2026-11-10')
            ->where('rows.0.can_edit', true)
            ->has('days', 30)
            ->has('legend')
        );
    }

    public function test_calendar_exposes_shift_time_source_and_manual_audit_data(): void
    {
        $context = $this->prepareContext();

        $this->makeSchedule($context, '2026-11-10', $context['pagi']);
        $this->makeSchedule($context, '2026-11-11', $context['malam'], [
            'source' => Schedule::SOURCE_ADJUSTMENT,
            'notes' => 'Penyesuaian kebutuhan operasional.',
            'updated_by' => $context['admin']->id,
        ]);
        $this->makeSchedule($context, '2026-11-12', $context['libur']);

        $response = $this->actingAs($context['admin'])->get('/admin/schedule-calendar?month=2026-11');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/ScheduleCalendar/Index')
            ->has('rows', 3)
            ->where('rows.0.date', '2026-11-10')
            ->where('rows.0.shift_time', '08:00 - 16:00')
            ->where('rows.0.source', Schedule::SOURCE_SYSTEM_GENERATED)
            ->where('rows.0.is_off', false)
            ->where('rows.1.source', Schedule::SOURCE_ADJUSTMENT)
            ->where('rows.1.shift_time', '20:00 - 04:00 (+1 hari)')
            ->where('rows.1.notes', 'Penyesuaian kebutuhan operasional.')
            ->where('rows.1.updated_by_name', $context['admin']->name)
            ->where('rows.2.is_off', true)
        );
    }

    public function test_filters_by_team_and_security(): void
    {
        $context = $this->prepareContext();

        $otherSecurity = $this->createSecurity($context['unit'], 'Satpam B');
        $otherMember = $context['period']->addMember($otherSecurity, $context['pattern']);

        $this->makeSchedule($context, '2026-11-10', $context['pagi']);
        $this->makeScheduleFor($context, $otherSecurity, $otherMember, '2026-11-10', $context['pagi']);

        $byTeam = $this->actingAs($context['admin'])->get(
            '/admin/schedule-calendar?month=2026-11&security_team_id='.$context['team']->id
        );
        $byTeam->assertOk();
        $byTeam->assertInertia(fn ($page) => $page->has('rows', 2));

        $bySecurity = $this->actingAs($context['admin'])->get(
            '/admin/schedule-calendar?month=2026-11&security_id='.$otherSecurity->id
        );
        $bySecurity->assertOk();
        $bySecurity->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.security_id', $otherSecurity->id)
        );
    }

    public function test_filters_by_post_and_unit_within_scope(): void
    {
        $context = $this->prepareContext();
        $other = $this->prepareContext('Unit B', 'B', 'Satpam B');

        $this->makeSchedule($context, '2026-11-10', $context['pagi']);
        $this->makeSchedule($other, '2026-11-10', $other['pagi']);

        $this->actingAs($context['admin'])
            ->get('/admin/schedule-calendar?month=2026-11&security_post_id='.$context['post']->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.unit_id', $context['unit']->id)
            );

        $this->actingAs($context['admin'])
            ->get('/admin/schedule-calendar?month=2026-11&unit_id='.$other['unit']->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.unit_id', $context['unit']->id)
            );
    }

    public function test_scoped_admin_cannot_see_schedules_from_another_unit(): void
    {
        $context = $this->prepareContext();
        $other = $this->prepareContext('Unit B', 'B', 'Satpam B');

        $this->makeSchedule($context, '2026-11-10', $context['pagi']);
        $this->makeSchedule($other, '2026-11-10', $other['pagi']);

        $this->actingAs($context['admin'])
            ->get('/admin/schedule-calendar?month=2026-11')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.unit_id', $context['unit']->id)
            );

        $this->actingAs($context['admin'])
            ->get('/admin/schedule-calendar?month=2026-11&security_id='.$other['security']->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.unit_id', $context['unit']->id)
            );
    }

    public function test_superadmin_can_see_all_units(): void
    {
        $context = $this->prepareContext();
        $other = $this->prepareContext('Unit B', 'B', 'Satpam B');

        $this->makeSchedule($context, '2026-11-10', $context['pagi']);
        $this->makeSchedule($other, '2026-11-10', $other['pagi']);

        $superadmin = User::factory()->create(['role' => 'superadmin', 'unit_id' => null]);

        $this->actingAs($superadmin)
            ->get('/admin/schedule-calendar?month=2026-11&unit_id='.$context['unit']->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('rows', 1)
                ->where('rows.0.unit_id', $context['unit']->id));

        $this->actingAs($superadmin)
            ->get('/admin/schedule-calendar?month=2026-11&unit_id='.$other['unit']->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('rows', 1)
                ->where('rows.0.unit_id', $other['unit']->id));
    }

    public function test_completed_period_does_not_expose_edit_action(): void
    {
        $context = $this->prepareContext();
        $context['period']->update(['status' => SchedulePeriod::STATUS_COMPLETED]);

        $this->makeSchedule($context, '2026-11-10', $context['pagi']);

        $this->actingAs($context['admin'])
            ->get('/admin/schedule-calendar?month=2026-11')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.can_edit', false)
                ->where('rows.0.period_status', SchedulePeriod::STATUS_COMPLETED)
            );
    }

    public function test_status_filter_limits_schedules(): void
    {
        $context = $this->prepareContext();

        $this->makeSchedule($context, '2026-11-10', $context['pagi']);

        $draftSecurity = $this->createSecurity($context['unit'], 'Satpam Draft');
        $draftPeriod = $this->createPeriod(
            $context['unit'],
            $context['post'],
            $context['team'],
            $context['admin'],
            '2026-10-01',
            '2026-10-31',
            SchedulePeriod::STATUS_DRAFT,
            'Periode Draft'
        );
        $draftMember = $draftPeriod->addMember($draftSecurity, $context['pattern']);
        $this->makeScheduleFor($context, $draftSecurity, $draftMember, '2026-11-11', $context['pagi'], [
            'schedule_period_id' => $draftPeriod->id,
        ]);

        $this->actingAs($context['admin'])
            ->get('/admin/schedule-calendar?month=2026-11&status='.SchedulePeriod::STATUS_ACTIVE)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.period_status', SchedulePeriod::STATUS_ACTIVE)
            );
    }

    public function test_security_role_is_forbidden(): void
    {
        $context = $this->prepareContext();
        $securityUser = User::factory()->create([
            'role' => 'security',
            'unit_id' => $context['unit']->id,
        ]);

        $this->actingAs($securityUser)
            ->get('/admin/schedule-calendar?month=2026-11')
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareContext(
        string $unitName = 'Unit A',
        string $slot = 'A',
        string $securityName = 'Satpam A'
    ): array {
        $unit = $this->createUnit();
        $unit->update(['unit_name' => $unitName]);

        $post = $this->createPost($unit);
        $team = $this->createTeam($post, $slot);
        $admin = $this->createAdmin($unit);

        $period = $this->createPeriod(
            $unit,
            $post,
            $team,
            $admin,
            '2026-11-01',
            '2026-11-30',
            SchedulePeriod::STATUS_ACTIVE,
            'Periode November'
        );

        $pagi = $this->createShift($unit, 'Pagi', 'PAGI', [
            'start_time' => '08:00',
            'end_time' => '16:00',
            'color' => '#22c55e',
        ]);
        $malam = $this->createShift($unit, 'Malam', 'MALAM', [
            'start_time' => '20:00',
            'end_time' => '04:00',
            'end_day_offset' => 1,
            'color' => '#3b82f6',
        ]);
        $libur = $this->createShift($unit, 'Libur', 'LIBUR', [
            'color' => '#e5e7eb',
        ]);

        $pattern = $this->createPattern($unit, 'Pola A');

        $security = $this->createSecurity($unit, $securityName);
        $member = $period->addMember($security, $pattern);

        return compact('unit', 'post', 'team', 'admin', 'period', 'pagi', 'malam', 'libur', 'pattern', 'security', 'member');
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $overrides
     */
    private function makeSchedule(array $context, string $date, Shift $shift, array $overrides = []): Schedule
    {
        return $this->makeScheduleFor($context, $context['security'], $context['member'], $date, $shift, $overrides);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $overrides
     */
    private function makeScheduleFor(
        array $context,
        Security $security,
        mixed $member,
        string $date,
        Shift $shift,
        array $overrides = []
    ): Schedule {
        return Schedule::create(array_merge([
            'schedule_period_id' => $context['period']->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'schedule_pattern_id' => $context['pattern']->id,
            'pattern_day_order' => 1,
            'date' => $date,
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $context['admin']->id,
        ], $overrides));
    }
}
