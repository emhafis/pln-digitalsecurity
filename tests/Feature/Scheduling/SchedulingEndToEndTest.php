<?php

namespace Tests\Feature\Scheduling;

use App\Models\Schedule;
use App\Models\SchedulePattern;
use App\Models\SchedulePeriod;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SchedulingEndToEndTest extends SchedulingTestCase
{
    use RefreshDatabase;

    public function test_full_flow_from_team_to_generate_manual_regenerate_and_calendar(): void
    {
        $context = $this->bootstrapFlow();
        $admin = $context['admin'];
        $period = $context['period'];

        $this->actingAs($admin)
            ->post("/admin/schedule-period/{$period->id}/generate", ['mode' => 'fill'])
            ->assertRedirect();

        $this->assertSame(90, Schedule::query()->where('schedule_period_id', $period->id)->count());

        $rotations = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->get();

        $this->assertTrue($rotations->every(fn (Schedule $schedule) => $schedule->source === Schedule::SOURCE_SYSTEM_GENERATED));

        $duplicates = Schedule::query()
            ->select('security_id', 'date')
            ->groupBy('security_id', 'date')
            ->havingRaw('count(*) > 1')
            ->get();

        $this->assertCount(0, $duplicates);

        $target = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->where('security_id', $context['security_a']->id)
            ->whereDate('date', '2026-11-03')
            ->firstOrFail();

        $this->assertSame($context['siang']->id, $target->shift_id);
        $this->assertSame(Schedule::SOURCE_SYSTEM_GENERATED, $target->source);

        $this->actingAs($admin)
            ->put("/admin/schedule-period/{$period->id}/schedule/{$target->id}", [
                'shift_id' => $context['malam']->id,
                'notes' => 'Penyesuaian kebutuhan operasional.',
            ])
            ->assertRedirect(route('admin.schedule-period.show', $period->id));

        $target->refresh();
        $this->assertSame($context['malam']->id, $target->shift_id);
        $this->assertSame(Schedule::SOURCE_ADJUSTMENT, $target->source);
        $this->assertSame($admin->id, $target->updated_by);
        $this->assertSame('Penyesuaian kebutuhan operasional.', $target->notes);

        $this->actingAs($admin)
            ->post("/admin/schedule-period/{$period->id}/generate", ['mode' => 'regenerate'])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame($context['malam']->id, $target->shift_id);
        $this->assertSame(Schedule::SOURCE_ADJUSTMENT, $target->source);

        $rotationRow = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->where('security_id', $context['security_a']->id)
            ->whereDate('date', '2026-11-04')
            ->firstOrFail();

        $this->assertSame($context['siang']->id, $rotationRow->shift_id);
        $this->assertSame(Schedule::SOURCE_SYSTEM_GENERATED, $rotationRow->source);

        $this->assertSame(90, Schedule::query()->where('schedule_period_id', $period->id)->count());

        $calendarCount = Schedule::query()
            ->whereDate('date', '>=', '2026-11-01')
            ->whereDate('date', '<=', '2026-11-30')
            ->count();

        $calendar = $this->actingAs($admin)->get('/admin/schedule-calendar?month=2026-11');

        $calendar->assertOk();
        $calendar->assertInertia(fn ($page) => $page
            ->component('Admin/ScheduleCalendar/Index')
            ->has('rows', $calendarCount)
            ->where('rows', function ($rows) use ($target) {
                $row = collect($rows)->firstWhere('id', $target->id);

                return $row !== null
                    && (int) $row['shift_id'] === (int) $target->shift_id
                    && $row['source'] === Schedule::SOURCE_ADJUSTMENT;
            })
        );
    }

    public function test_december_period_keeps_november_snapshot_and_regenerate_does_not_touch_other_period(): void
    {
        $context = $this->bootstrapFlow();
        $admin = $context['admin'];
        $november = $context['period'];

        $this->actingAs($admin)
            ->post("/admin/schedule-period/{$november->id}/generate", ['mode' => 'fill'])
            ->assertRedirect();

        $novemberMemberIds = $november->members()->orderBy('security_id')->pluck('security_id')->map(fn ($id) => (int) $id)->all();

        $securityD = $this->createSecurity($context['unit'], 'Satpam D');
        $context['team']->assignMember($securityD, '2026-12-01');

        $this->actingAs($admin)
            ->post('/admin/schedule-period', [
                'unit_id' => $context['unit']->id,
                'security_post_id' => $context['post']->id,
                'security_team_id' => $context['team']->id,
                'name' => 'Periode Desember 2026',
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-31',
                'status' => SchedulePeriod::STATUS_DRAFT,
                'members' => [
                    ['security_id' => $context['security_a']->id, 'schedule_pattern_id' => $context['pattern']->id, 'start_offset' => 0],
                    ['security_id' => $context['security_b']->id, 'schedule_pattern_id' => $context['pattern']->id, 'start_offset' => 2],
                    ['security_id' => $securityD->id, 'schedule_pattern_id' => $context['pattern']->id, 'start_offset' => 6],
                ],
            ])
            ->assertRedirect();

        $december = SchedulePeriod::query()->where('name', 'Periode Desember 2026')->firstOrFail();

        $this->assertSame(
            $novemberMemberIds,
            $november->fresh()->members()->orderBy('security_id')->pluck('security_id')->map(fn ($id) => (int) $id)->all()
        );
        $this->assertSame(3, $november->fresh()->members()->count());
        $this->assertSame(
            [$context['security_a']->id, $context['security_b']->id, $securityD->id],
            $december->members()->orderBy('security_id')->pluck('security_id')->map(fn ($id) => (int) $id)->all()
        );

        $this->actingAs($admin)
            ->post("/admin/schedule-period/{$december->id}/generate", ['mode' => 'fill'])
            ->assertRedirect();

        $decemberSnapshot = Schedule::query()
            ->where('schedule_period_id', $december->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Schedule $schedule) => [
                'id' => $schedule->id,
                'shift_id' => $schedule->shift_id,
                'source' => $schedule->source,
                'date' => $schedule->date->toDateString(),
            ])
            ->all();

        $this->actingAs($admin)
            ->post("/admin/schedule-period/{$november->id}/generate", ['mode' => 'regenerate'])
            ->assertRedirect();

        $decemberAfter = Schedule::query()
            ->where('schedule_period_id', $december->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Schedule $schedule) => [
                'id' => $schedule->id,
                'shift_id' => $schedule->shift_id,
                'source' => $schedule->source,
                'date' => $schedule->date->toDateString(),
            ])
            ->all();

        $this->assertSame($decemberSnapshot, $decemberAfter);
    }

    public function test_master_pattern_and_shift_changes_do_not_modify_existing_schedules(): void
    {
        $context = $this->bootstrapFlow();
        $admin = $context['admin'];
        $period = $context['period'];

        $this->actingAs($admin)
            ->post("/admin/schedule-period/{$period->id}/generate", ['mode' => 'fill'])
            ->assertRedirect();

        $sample = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->where('security_id', $context['security_a']->id)
            ->whereDate('date', '2026-11-01')
            ->firstOrFail();

        $originalShiftId = $sample->shift_id;
        $originalDayOrder = $sample->pattern_day_order;

        $this->actingAs($admin)
            ->put("/admin/schedule-pattern/{$context['pattern']->id}", [
                'unit_id' => $context['unit']->id,
                'name' => $context['pattern']->name,
                'description' => $context['pattern']->description,
                'cycle_length' => 8,
                'is_active' => true,
                'details' => [
                    ['day_order' => 1, 'shift_id' => $context['siang']->id],
                    ['day_order' => 2, 'shift_id' => $context['pagi']->id],
                    ['day_order' => 3, 'shift_id' => $context['siang']->id],
                    ['day_order' => 4, 'shift_id' => $context['siang']->id],
                    ['day_order' => 5, 'shift_id' => $context['malam']->id],
                    ['day_order' => 6, 'shift_id' => $context['malam']->id],
                    ['day_order' => 7, 'shift_id' => $context['libur']->id],
                    ['day_order' => 8, 'shift_id' => $context['libur']->id],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->put("/admin/shift/{$context['pagi']->id}", [
                'unit_id' => $context['unit']->id,
                'name' => 'Siang',
                'code' => $context['pagi']->code,
                'start_time' => '07:00',
                'end_time' => '15:00',
                'end_day_offset' => false,
                'is_active' => true,
            ])
            ->assertRedirect();

        $sample->refresh();
        $this->assertSame($originalShiftId, $sample->shift_id);
        $this->assertSame($originalDayOrder, $sample->pattern_day_order);
        $this->assertSame(Schedule::SOURCE_SYSTEM_GENERATED, $sample->source);
    }

    public function test_referenced_master_data_cannot_be_deleted(): void
    {
        $context = $this->bootstrapFlow();
        $admin = $context['admin'];
        $period = $context['period'];

        $this->actingAs($admin)
            ->post("/admin/schedule-period/{$period->id}/generate", ['mode' => 'fill'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->from('/admin/shift')
            ->delete("/admin/shift/{$context['pagi']->id}")
            ->assertRedirect('/admin/shift')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('shifts', ['id' => $context['pagi']->id]);

        $this->actingAs($admin)
            ->from('/admin/schedule-pattern')
            ->delete("/admin/schedule-pattern/{$context['pattern']->id}")
            ->assertRedirect('/admin/schedule-pattern')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('schedule_patterns', ['id' => $context['pattern']->id]);

        $this->actingAs($admin)
            ->from('/admin/security')
            ->delete("/admin/security/{$context['security_a']->id}")
            ->assertRedirect('/admin/security')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('securities', ['id' => $context['security_a']->id]);
    }

    /**
     * Build scenarios A-E: unit, post, team, members, shifts, 2-2-2-2 pattern, and November period.
     *
     * @return array<string, mixed>
     */
    private function bootstrapFlow(): array
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post, 'A');
        $admin = $this->createAdmin($unit);

        $securityA = $this->createSecurity($unit, 'Satpam A');
        $securityB = $this->createSecurity($unit, 'Satpam B');
        $securityC = $this->createSecurity($unit, 'Satpam C');

        foreach ([$securityA, $securityB, $securityC] as $security) {
            $team->assignMember($security, '2026-10-01');
        }

        $pagi = $this->createShiftViaHttp($admin, $unit, 'Pagi', 'PAGI', '08:00', '16:00', false);
        $siang = $this->createShiftViaHttp($admin, $unit, 'Siang', 'SIANG', '12:00', '20:00', false);
        $malam = $this->createShiftViaHttp($admin, $unit, 'Malam', 'MALAM', '20:00', '04:00', true);
        $libur = $this->createShiftViaHttp($admin, $unit,
            'Lepas Dinas', 'LEPAS', null, null, false);

        $this->actingAs($admin)
            ->post('/admin/schedule-pattern', [
                'unit_id' => $unit->id,
                'name' => 'Pola 2-2-2-2',
                'description' => 'Pola rotasi QA',
                'cycle_length' => 8,
                'is_active' => true,
                'details' => [
                    ['day_order' => 1, 'shift_id' => $pagi->id],
                    ['day_order' => 2, 'shift_id' => $pagi->id],
                    ['day_order' => 3, 'shift_id' => $siang->id],
                    ['day_order' => 4, 'shift_id' => $siang->id],
                    ['day_order' => 5, 'shift_id' => $malam->id],
                    ['day_order' => 6, 'shift_id' => $malam->id],
                    ['day_order' => 7, 'shift_id' => $libur->id],
                    ['day_order' => 8, 'shift_id' => $libur->id],
                ],
            ])
            ->assertRedirect();

        $pattern = SchedulePattern::query()->where('unit_id', $unit->id)->where('name', 'Pola 2-2-2-2')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/schedule-period', [
                'unit_id' => $unit->id,
                'security_post_id' => $post->id,
                'security_team_id' => $team->id,
                'name' => 'Periode November 2026',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-30',
                'status' => SchedulePeriod::STATUS_ACTIVE,
                'members' => [
                    ['security_id' => $securityA->id, 'schedule_pattern_id' => $pattern->id, 'start_offset' => 0],
                    ['security_id' => $securityB->id, 'schedule_pattern_id' => $pattern->id, 'start_offset' => 2],
                    ['security_id' => $securityC->id, 'schedule_pattern_id' => $pattern->id, 'start_offset' => 4],
                ],
            ])
            ->assertRedirect();

        $period = SchedulePeriod::query()->where('name', 'Periode November 2026')->firstOrFail();

        $this->assertSame(3, $period->members()->count());

        return [
            'unit' => $unit,
            'post' => $post,
            'team' => $team,
            'admin' => $admin,
            'period' => $period,
            'pattern' => $pattern,
            'pagi' => $pagi,
            'siang' => $siang,
            'malam' => $malam,
            'libur' => $libur,
            'security_a' => $securityA,
            'security_b' => $securityB,
            'security_c' => $securityC,
        ];
    }

    private function createShiftViaHttp(
        User $admin,
        Unit $unit,
        string $name,
        string $code,
        ?string $startTime,
        ?string $endTime,
        bool $endDayOffset
    ): Shift {
        $this->actingAs($admin)
            ->post('/admin/shift', [
                'unit_id' => $unit->id,
                'name' => $name,
                'code' => $code,
                'type' => $startTime === null ? 'day_off' : 'working',
                'start_time' => $startTime,
                'end_time' => $endTime,
                'end_day_offset' => $endDayOffset,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.shift.index'));

        return Shift::query()
            ->where('unit_id', $unit->id)
            ->where('name', $name)
            ->firstOrFail();
    }
}
