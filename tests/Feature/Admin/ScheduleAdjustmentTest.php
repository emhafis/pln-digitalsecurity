<?php

namespace Tests\Feature\Admin;

use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Scheduling\SchedulingTestCase;

class ScheduleAdjustmentTest extends SchedulingTestCase
{
    use RefreshDatabase;

    public function test_manual_adjustment_updates_single_row_and_marks_manual(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();

        $target = $schedules->first();

        $response = $this->actingAs($admin)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$target->id}",
            ['shift_id' => $replacement->id, 'notes' => 'Penyesuaian kebutuhan operasional.']
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame($replacement->id, $target->shift_id);
        $this->assertSame(Schedule::SOURCE_ADJUSTMENT, $target->source);
        $this->assertSame('Penyesuaian kebutuhan operasional.', $target->notes);
        $this->assertSame($admin->id, $target->updated_by);

        $others = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->whereKeyNot($target->id)
            ->get();
        foreach ($others as $schedule) {
            $this->assertSame(Schedule::SOURCE_SYSTEM_GENERATED, $schedule->source);
        }
    }

    public function test_adjustment_preserves_rotation_metadata_and_date(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();
        $target = $schedules->first();
        $originalDate = $target->date->toDateString();
        $originalPatternId = $target->schedule_pattern_id;
        $originalDayOrder = $target->pattern_day_order;

        $this->actingAs($admin)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$target->id}",
            ['shift_id' => $replacement->id, 'notes' => 'Alasan.']
        );

        $target->refresh();
        $this->assertSame($originalDate, $target->date->toDateString());
        $this->assertSame($originalPatternId, $target->schedule_pattern_id);
        $this->assertSame($originalDayOrder, $target->pattern_day_order);
    }

    public function test_snapshot_unchanged_after_adjustment(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();
        $before = $period->members()->count();

        $this->actingAs($admin)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$schedules->first()->id}",
            ['shift_id' => $replacement->id, 'notes' => 'Alasan.']
        );

        $this->assertSame($before, $period->members()->count());
    }

    public function test_shift_from_another_unit_rejected(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();
        $otherUnit = $this->createUnit();
        $otherShift = $this->createShift($otherUnit, 'Shift Lain', 'LAIN');

        $response = $this->actingAs($admin)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$schedules->first()->id}",
            ['shift_id' => $otherShift->id, 'notes' => 'Alasan.']
        );

        $response->assertSessionHasErrors('shift_id');
        $this->assertSame(Schedule::SOURCE_SYSTEM_GENERATED, $schedules->first()->fresh()->source);
    }

    public function test_inactive_shift_rejected(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();
        $replacement->update(['is_active' => false]);

        $response = $this->actingAs($admin)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$schedules->first()->id}",
            ['shift_id' => $replacement->id, 'notes' => 'Alasan.']
        );

        $response->assertSessionHasErrors('shift_id');
    }

    public function test_schedule_from_other_period_cannot_be_adjusted_through_current_period(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();

        $unitB = $this->createUnit();
        $postB = $this->createPost($unitB);
        $teamB = $this->createTeam($postB, 'B');
        $adminB = $this->createAdmin($unitB);
        $periodB = $this->createPeriod($unitB, $postB, $teamB, $adminB, '2026-11-01', '2026-11-04');
        $shiftB = $this->createShift($unitB, 'Pagi', 'PAGI');
        $patternB = $this->createPattern($unitB, 'Pola B');
        $securityB = $this->createSecurity($unitB, 'Satpam B');
        $periodB->addMember($securityB, $patternB);
        $foreign = Schedule::create([
            'schedule_period_id' => $periodB->id,
            'schedule_period_member_id' => $periodB->members()->first()->id,
            'security_id' => $securityB->id,
            'shift_id' => $shiftB->id,
            'schedule_pattern_id' => $patternB->id,
            'pattern_day_order' => 1,
            'date' => '2026-11-01',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $adminB->id,
        ]);

        $response = $this->actingAs($admin)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$foreign->id}",
            ['shift_id' => $replacement->id, 'notes' => 'Alasan.']
        );

        $response->assertNotFound();
    }

    public function test_notes_required(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();

        $response = $this->actingAs($admin)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$schedules->first()->id}",
            ['shift_id' => $replacement->id, 'notes' => '   ']
        );

        $response->assertSessionHasErrors('notes');
    }

    public function test_same_shift_rejected(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();
        $target = $schedules->first();

        $response = $this->actingAs($admin)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$target->id}",
            ['shift_id' => $target->shift_id, 'notes' => 'Alasan.']
        );

        $response->assertSessionHasErrors('shift_id');
    }

    public function test_completed_period_rejects_adjustment(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();
        $period->update(['status' => SchedulePeriod::STATUS_COMPLETED]);

        $response = $this->actingAs($admin)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$schedules->first()->id}",
            ['shift_id' => $replacement->id, 'notes' => 'Alasan.']
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('schedule');
    }

    public function test_out_of_scope_admin_rejected(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();
        $otherUnit = $this->createUnit();
        $otherAdmin = $this->createAdmin($otherUnit);

        $response = $this->actingAs($otherAdmin)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$schedules->first()->id}",
            ['shift_id' => $replacement->id, 'notes' => 'Alasan.']
        );

        $response->assertForbidden();
    }

    public function test_security_role_rejected(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();
        $securityUser = User::factory()->create(['role' => 'security', 'unit_id' => $period->unit_id]);

        $response = $this->actingAs($securityUser)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$schedules->first()->id}",
            ['shift_id' => $replacement->id, 'notes' => 'Alasan.']
        );

        $response->assertForbidden();
    }

    public function test_manual_schedule_survives_regenerate(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();
        $target = $schedules->first();

        $this->actingAs($admin)->put(
            "/admin/schedule-period/{$period->id}/schedule/{$target->id}",
            ['shift_id' => $replacement->id, 'notes' => 'Alasan.']
        );

        $this->actingAs($admin)->post("/admin/schedule-period/{$period->id}/generate", ['mode' => 'regenerate']);

        $target->refresh();
        $this->assertSame($replacement->id, $target->shift_id);
        $this->assertSame(Schedule::SOURCE_ADJUSTMENT, $target->source);
    }

    public function test_show_page_renders_schedules(): void
    {
        [$period, $admin, $schedules, $replacement] = $this->prepareContext();

        $response = $this->actingAs($admin)->get("/admin/schedule-period/{$period->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/SchedulePeriod/Show')
            ->has('schedules', $schedules->count())
            ->has('shiftOptions')
            ->where('isLocked', false)
        );
    }

    private function prepareContext(): array
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-04', SchedulePeriod::STATUS_DRAFT, 'Periode Adjustment');

        $shifts = [
            'pagi' => $this->createShift($unit, 'Pagi', 'PAGI'),
            'siang' => $this->createShift($unit, 'Siang', 'SIANG'),
            'malam' => $this->createShift($unit, 'Malam', 'MALAM', ['end_day_offset' => 1]),
            'libur' => $this->createShift($unit, 'Libur', 'LIBUR'),
        ];
        $replacement = $shifts['malam'];

        $pattern = $this->createPattern($unit, 'Pola 4 Hari');
        $pattern->addDetail($shifts['pagi'], 1);
        $pattern->addDetail($shifts['siang'], 2);
        $pattern->addDetail($shifts['malam'], 3);
        $pattern->addDetail($shifts['libur'], 4);

        $security = $this->createSecurity($unit, 'Satpam A');
        $member = $period->addMember($security, $pattern);

        foreach (['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'] as $date) {
            Schedule::create([
                'schedule_period_id' => $period->id,
                'schedule_period_member_id' => $member->id,
                'security_id' => $security->id,
                'shift_id' => $shifts['pagi']->id,
                'schedule_pattern_id' => $pattern->id,
                'pattern_day_order' => 1,
                'date' => $date,
                'source' => Schedule::SOURCE_SYSTEM_GENERATED,
                'created_by' => $admin->id,
            ]);
        }

        $schedules = Schedule::query()->where('schedule_period_id', $period->id)->orderBy('date')->get();

        return [$period, $admin, $schedules, $replacement];
    }
}
