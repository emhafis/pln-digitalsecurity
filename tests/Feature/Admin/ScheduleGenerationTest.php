<?php

namespace Tests\Feature\Admin;

use App\Models\Schedule;
use App\Models\SchedulePattern;
use App\Models\SchedulePeriod;
use App\Models\SchedulePeriodMember;
use App\Models\Security;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Scheduling\SchedulingTestCase;

class ScheduleGenerationTest extends SchedulingTestCase
{
    public function test_generator_follows_two_two_two_two_pattern(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-08');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['malam'],
            $shifts['libur'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $member = $period->addMember($security, $pattern, 0);

        $response = $this->generate($period, $admin);

        $response->assertSessionHasNoErrors();

        $schedules = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->orderBy('date')
            ->get();

        $this->assertCount(8, $schedules);
        $this->assertSame(
            [
                $shifts['pagi']->id,
                $shifts['pagi']->id,
                $shifts['siang']->id,
                $shifts['siang']->id,
                $shifts['malam']->id,
                $shifts['malam']->id,
                $shifts['libur']->id,
                $shifts['libur']->id,
            ],
            $schedules->pluck('shift_id')->all()
        );
        $this->assertSame(range(1, 8), $schedules->pluck('pattern_day_order')->all());
        $this->assertSame(
            array_fill(0, 8, Schedule::SOURCE_SYSTEM_GENERATED),
            $schedules->pluck('source')->all()
        );
        $this->assertSame(
            array_fill(0, 8, $security->id),
            $schedules->pluck('security_id')->all()
        );
        $this->assertSame(
            array_fill(0, 8, $member->id),
            $schedules->pluck('schedule_period_member_id')->all()
        );
        $this->assertSame(
            array_fill(0, 8, $pattern->id),
            $schedules->pluck('schedule_pattern_id')->all()
        );
        $this->assertSame(
            array_fill(0, 8, $admin->id),
            $schedules->pluck('created_by')->all()
        );
        $this->assertCount(
            8,
            $schedules
                ->groupBy(fn (Schedule $schedule) => $schedule->security_id.'|'.$schedule->date->toDateString())
                ->all()
        );
    }

    public function test_cycle_repeats_across_multiple_cycles(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-10');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security, $pattern);

        $this->generate($period, $admin)->assertSessionHasNoErrors();

        $schedules = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->orderBy('date')
            ->get();

        $this->assertCount(10, $schedules);
        $this->assertSame(
            [1, 2, 3, 4, 1, 2, 3, 4, 1, 2],
            $schedules->pluck('pattern_day_order')->all()
        );
        $this->assertSame(
            [
                $shifts['pagi']->id,
                $shifts['siang']->id,
                $shifts['malam']->id,
                $shifts['libur']->id,
                $shifts['pagi']->id,
                $shifts['siang']->id,
                $shifts['malam']->id,
                $shifts['libur']->id,
                $shifts['pagi']->id,
                $shifts['siang']->id,
            ],
            $schedules->pluck('shift_id')->all()
        );
    }

    public function test_different_offsets_produce_different_sequences(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $a = $this->createSecurity($unit, 'Satpam A');
        $b = $this->createSecurity($unit, 'Satpam B');
        $period->addMember($a, $pattern, 0);
        $period->addMember($b, $pattern, 2);

        $this->generate($period, $admin)->assertSessionHasNoErrors();

        $aShifts = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->where('security_id', $a->id)
            ->orderBy('date')
            ->pluck('shift_id')
            ->all();
        $bShifts = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->where('security_id', $b->id)
            ->orderBy('date')
            ->pluck('shift_id')
            ->all();
        $bOrders = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->where('security_id', $b->id)
            ->orderBy('date')
            ->pluck('pattern_day_order')
            ->all();

        $this->assertSame(
            [$shifts['pagi']->id, $shifts['siang']->id, $shifts['malam']->id, $shifts['libur']->id],
            $aShifts
        );
        $this->assertSame(
            [$shifts['malam']->id, $shifts['libur']->id, $shifts['pagi']->id, $shifts['siang']->id],
            $bShifts
        );
        $this->assertSame([3, 4, 1, 2], $bOrders);
    }

    public function test_members_can_use_different_patterns(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-06');
        $shifts = $this->makeShifts($unit);
        $patternA = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
        ], 'Pola 2 Hari');
        $patternB = $this->makePattern($unit, [
            $shifts['malam'],
            $shifts['libur'],
            $shifts['pagi'],
        ], 'Pola 3 Hari');
        $a = $this->createSecurity($unit, 'Satpam A');
        $b = $this->createSecurity($unit, 'Satpam B');
        $period->addMember($a, $patternA);
        $period->addMember($b, $patternB);

        $this->generate($period, $admin)->assertSessionHasNoErrors();

        $aShifts = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->where('security_id', $a->id)
            ->orderBy('date')
            ->pluck('shift_id')
            ->all();
        $bShifts = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->where('security_id', $b->id)
            ->orderBy('date')
            ->pluck('shift_id')
            ->all();

        $this->assertSame(
            [
                $shifts['pagi']->id,
                $shifts['siang']->id,
                $shifts['pagi']->id,
                $shifts['siang']->id,
                $shifts['pagi']->id,
                $shifts['siang']->id,
            ],
            $aShifts
        );
        $this->assertSame(
            [
                $shifts['malam']->id,
                $shifts['libur']->id,
                $shifts['pagi']->id,
                $shifts['malam']->id,
                $shifts['libur']->id,
                $shifts['pagi']->id,
            ],
            $bShifts
        );
    }

    public function test_preview_does_not_persist_and_reports_summary(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security, $pattern);

        $response = $this->actingAs($admin)->get(
            "/admin/schedule-period/{$period->id}/generate?mode=fill"
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/Generate')
            ->where('preview.mode', 'fill')
            ->where('preview.summary.members_count', 1)
            ->where('preview.summary.days_count', 4)
            ->where('preview.summary.total_candidates', 4)
            ->where('preview.summary.create_count', 4)
            ->where('preview.summary.total_changes', 4)
            ->where('preview.errors', [])
            ->has('preview.rows', 4)
            ->where('preview.rows.0.action', 'create')
        );

        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_default_generate_only_fills_missing_schedules(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security, $pattern);

        $this->generate($period, $admin)->assertSessionHasNoErrors();

        $existing = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->orderBy('date')
            ->get();
        $deletedId = $existing
            ->first(fn (Schedule $schedule) => $schedule->date->toDateString() === '2026-11-02')
            ->id;
        $remainingIds = $existing->where('id', '!=', $deletedId)->pluck('id')->all();
        Schedule::query()->whereKey($deletedId)->delete();

        $response = $this->generate($period, $admin);

        $response->assertSessionHasNoErrors();

        $after = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->orderBy('date')
            ->get();

        $this->assertCount(4, $after);
        $this->assertCount(4, $after->pluck('date')->unique());
        $this->assertEmpty(array_diff($remainingIds, $after->pluck('id')->all()));
        $this->assertNotContains($deletedId, $after->pluck('id')->all());
        $this->assertSame(1, session('generation_result')['created']);
        $this->assertSame(3, session('generation_result')['skipped']);
    }

    public function test_generate_preserves_existing_manual_schedule(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $member = $period->addMember($security, $pattern);
        $manual = $this->createManualSchedule($period, $member, $security, $admin, $shifts['libur'], '2026-11-02');

        $response = $this->generate($period, $admin);

        $response->assertSessionHasNoErrors();

        $manual->refresh();
        $this->assertSame($shifts['libur']->id, $manual->shift_id);
        $this->assertSame(Schedule::SOURCE_ADJUSTMENT, $manual->source);
        $this->assertSame(1, session('generation_result')['manual_preserved']);
        $this->assertSame(3, session('generation_result')['created']);
        $this->assertSame(4, Schedule::query()->where('schedule_period_id', $period->id)->count());
        $this->assertSame(
            3,
            Schedule::query()
                ->where('schedule_period_id', $period->id)
                ->where('source', Schedule::SOURCE_SYSTEM_GENERATED)
                ->count()
        );
    }

    public function test_regenerate_updates_only_rotation_and_keeps_manual(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $member = $period->addMember($security, $pattern);

        $this->generate($period, $admin)->assertSessionHasNoErrors();

        $manual = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->whereDate('date', '2026-11-02')
            ->firstOrFail();
        $manual->update([
            'shift_id' => $shifts['libur']->id,
            'source' => Schedule::SOURCE_ADJUSTMENT,
        ]);

        foreach ($pattern->details as $detail) {
            $detail->update(['shift_id' => $shifts['malam']->id]);
        }

        $previewResponse = $this->actingAs($admin)->get(
            "/admin/schedule-period/{$period->id}/generate?mode=regenerate"
        );

        $previewResponse->assertOk();
        $previewResponse->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/Generate')
            ->where('preview.mode', 'regenerate')
            ->where('preview.summary.update_count', 2)
            ->where('preview.summary.manual_count', 1)
            ->where('preview.summary.unchanged_count', 1)
            ->where('preview.summary.create_count', 0)
            ->where('preview.summary.delete_count', 0)
            ->has('preview.rows', 4)
            ->where('preview.rows.0.action', 'update')
            ->where('preview.rows.0.shift_name', 'Malam')
            ->where('preview.rows.0.existing_shift_name', 'Pagi')
            ->where('preview.rows.1.action', 'keep_manual')
        );

        $response = $this->generate($period, $admin, 'regenerate');

        $response->assertSessionHasNoErrors();
        $this->assertSame(2, session('generation_result')['updated']);
        $this->assertSame(1, session('generation_result')['manual_preserved']);

        $manual->refresh();
        $this->assertSame($shifts['libur']->id, $manual->shift_id);
        $this->assertSame(Schedule::SOURCE_ADJUSTMENT, $manual->source);

        $rotationSchedules = Schedule::query()
            ->where('schedule_period_id', $period->id)
            ->where('source', Schedule::SOURCE_SYSTEM_GENERATED)
            ->get();

        $this->assertCount(3, $rotationSchedules);
        $this->assertSame(
            array_fill(0, 3, $shifts['malam']->id),
            $rotationSchedules->pluck('shift_id')->all()
        );
        $this->assertSame(
            2,
            $rotationSchedules->filter(fn (Schedule $schedule) => $schedule->updated_by === $admin->id)->count()
        );
        $this->assertSame(4, Schedule::query()->where('schedule_period_id', $period->id)->count());
    }

    public function test_generate_does_not_change_other_period_schedules(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security, $pattern);

        $teamB = $this->createTeam($post, 'B');
        $periodB = $this->createPeriod(
            $unit,
            $post,
            $teamB,
            $admin,
            '2026-12-01',
            '2026-12-04',
            SchedulePeriod::STATUS_DRAFT,
            'Desember'
        );
        $securityB = $this->createSecurity($unit, 'Satpam B');
        $memberB = $periodB->addMember($securityB, $pattern);
        $scheduleB = $this->createManualSchedule(
            $periodB,
            $memberB,
            $securityB,
            $admin,
            $shifts['pagi'],
            '2026-12-01',
            Schedule::SOURCE_SYSTEM_GENERATED
        );

        $this->generate($period, $admin)->assertSessionHasNoErrors();

        $scheduleB->refresh();

        $this->assertSame($periodB->id, $scheduleB->schedule_period_id);
        $this->assertSame($shifts['pagi']->id, $scheduleB->shift_id);
        $this->assertSame('2026-12-01', $scheduleB->date->toDateString());
        $this->assertSame(Schedule::SOURCE_SYSTEM_GENERATED, $scheduleB->source);
        $this->assertSame(1, Schedule::query()->where('schedule_period_id', $periodB->id)->count());
    }

    public function test_generate_does_not_change_pattern_or_shift_master(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security, $pattern);

        $beforeDetails = $pattern->details()->orderBy('day_order')->pluck('shift_id')->all();
        $beforeShift = $shifts['pagi']->only(['name', 'code', 'start_time', 'end_time', 'end_day_offset', 'is_active']);

        $this->generate($period, $admin)->assertSessionHasNoErrors();

        $this->assertSame(
            $beforeDetails,
            $pattern->fresh()->details()->orderBy('day_order')->pluck('shift_id')->all()
        );
        $this->assertSame($beforeShift, $shifts['pagi']->fresh()->only(array_keys($beforeShift)));
    }

    public function test_empty_snapshot_is_rejected_without_partial_schedules(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');

        $previewResponse = $this->actingAs($admin)->get(
            "/admin/schedule-period/{$period->id}/generate"
        );

        $previewResponse->assertOk();
        $previewResponse->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SchedulePeriod/Generate')
            ->where('preview.errors.0', 'Periode belum memiliki snapshot anggota.')
            ->where('preview.summary.create_count', 0)
            ->has('preview.rows', 0)
        );

        $response = $this->generate($period, $admin);

        $response->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_member_without_pattern_is_rejected(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security);

        $response = $this->generate($period, $admin);

        $response->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_one_invalid_member_aborts_generation_for_all_members(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $valid = $this->createSecurity($unit, 'Satpam Valid');
        $invalid = $this->createSecurity($unit, 'Satpam Tanpa Pola');
        $period->addMember($valid, $pattern);
        $period->addMember($invalid);

        $response = $this->generate($period, $admin);

        $response->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_inactive_pattern_is_rejected(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security, $pattern);
        $pattern->update(['is_active' => false]);

        $response = $this->generate($period, $admin);

        $response->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_inactive_shift_is_rejected(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security, $pattern);
        $shifts['pagi']->update(['is_active' => false]);

        $response = $this->generate($period, $admin);

        $response->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_incomplete_pattern_details_are_rejected(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => 'Pola Tidak Lengkap',
            'cycle_length' => 4,
            'is_active' => true,
        ]);
        $pattern->addDetail($shifts['pagi'], 1);
        $pattern->addDetail($shifts['siang'], 2);
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security, $pattern);

        $response = $this->generate($period, $admin);

        $response->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_offset_outside_pattern_cycle_is_rejected(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $member = $period->addMember($security, $pattern);
        DB::table('schedule_period_members')
            ->where('id', $member->id)
            ->update(['start_offset' => 4]);

        $response = $this->generate($period, $admin);

        $response->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_pattern_from_another_unit_is_rejected(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $otherUnit = $this->createUnit();
        $shifts = $this->makeShifts($unit);
        $otherShifts = $this->makeShifts($otherUnit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $foreignPattern = $this->makePattern($otherUnit, [
            $otherShifts['pagi'],
            $otherShifts['siang'],
            $otherShifts['malam'],
            $otherShifts['libur'],
        ], 'Pola Unit Lain');
        $security = $this->createSecurity($unit, 'Satpam A');
        $member = $period->addMember($security, $pattern);
        DB::table('schedule_period_members')
            ->where('id', $member->id)
            ->update(['schedule_pattern_id' => $foreignPattern->id]);

        $response = $this->generate($period, $admin);

        $response->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_shift_from_another_unit_is_rejected(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $otherUnit = $this->createUnit();
        $shifts = $this->makeShifts($unit);
        $otherShifts = $this->makeShifts($otherUnit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security, $pattern);

        $firstDetail = $pattern->details()->orderBy('day_order')->firstOrFail();
        DB::table('schedule_pattern_details')
            ->where('id', $firstDetail->id)
            ->update(['shift_id' => $otherShifts['pagi']->id]);

        $response = $this->generate($period, $admin);

        $response->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_schedule_conflict_in_another_period_aborts_all_generation(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security, $pattern);

        $teamB = $this->createTeam($post, 'B');
        $periodB = $this->createPeriod(
            $unit,
            $post,
            $teamB,
            $admin,
            '2026-10-01',
            '2026-10-31',
            SchedulePeriod::STATUS_COMPLETED,
            'Oktober'
        );
        $memberB = $periodB->addMember($security, $pattern);
        $other = $this->createManualSchedule(
            $periodB,
            $memberB,
            $security,
            $admin,
            $shifts['pagi'],
            '2026-11-01',
            Schedule::SOURCE_SYSTEM_GENERATED
        );

        $response = $this->generate($period, $admin);

        $response->assertSessionHasErrors('generation');
        $this->assertSame(1, Schedule::query()->count());
        $this->assertSame($other->id, Schedule::query()->first()->id);
        $this->assertDatabaseMissing('schedules', [
            'schedule_period_id' => $period->id,
        ]);
    }

    public function test_generate_is_forbidden_for_security_role_and_out_of_scope_admin(): void
    {
        [$unitA, $postA, $teamA, $adminA, $periodA] = $this->makeContext('2026-11-01', '2026-11-04');
        $unitB = $this->createUnit();
        $postB = $this->createPost($unitB);
        $teamB = $this->createTeam($postB);
        $adminB = $this->createAdmin($unitB);
        $periodB = $this->createPeriod(
            $unitB,
            $postB,
            $teamB,
            $adminB,
            '2026-11-01',
            '2026-11-04',
            SchedulePeriod::STATUS_DRAFT,
            'Unit B'
        );

        $securityUser = User::factory()->create([
            'role' => 'security',
            'unit_id' => $unitA->id,
        ]);

        $this->actingAs($securityUser)
            ->get("/admin/schedule-period/{$periodA->id}/generate")
            ->assertForbidden();
        $this->actingAs($securityUser)
            ->post("/admin/schedule-period/{$periodA->id}/generate", ['mode' => 'fill'])
            ->assertForbidden();

        $this->actingAs($adminA)
            ->get("/admin/schedule-period/{$periodB->id}/generate")
            ->assertForbidden();
        $this->actingAs($adminA)
            ->post("/admin/schedule-period/{$periodB->id}/generate", ['mode' => 'fill'])
            ->assertForbidden();
    }

    public function test_generation_rolls_back_when_a_write_fails(): void
    {
        [$unit, $post, $team, $admin, $period] = $this->makeContext('2026-11-01', '2026-11-04');
        $shifts = $this->makeShifts($unit);
        $pattern = $this->makePattern($unit, [
            $shifts['pagi'],
            $shifts['siang'],
            $shifts['malam'],
            $shifts['libur'],
        ]);
        $security = $this->createSecurity($unit, 'Satpam A');
        $period->addMember($security, $pattern);

        $created = 0;
        $event = 'eloquent.creating: '.Schedule::class;

        Event::listen($event, function () use (&$created) {
            $created++;

            if ($created === 3) {
                throw new \RuntimeException('Simulasi kegagalan generate.');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->post(
                "/admin/schedule-period/{$period->id}/generate",
                ['mode' => 'fill']
            );

            $this->fail('Generate seharusnya gagal.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulasi kegagalan generate.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertDatabaseCount('schedules', 0);
    }

    /**
     * @return array{0: Unit, 1: SecurityPost, 2: SecurityTeam, 3: User, 4: SchedulePeriod}
     */
    private function makeContext(
        string $startDate = '2026-11-01',
        string $endDate = '2026-11-04'
    ): array {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);
        $period = $this->createPeriod(
            $unit,
            $post,
            $team,
            $admin,
            $startDate,
            $endDate,
            SchedulePeriod::STATUS_DRAFT,
            'Periode Generator'
        );

        return [$unit, $post, $team, $admin, $period];
    }

    /**
     * @return array{pagi: Shift, siang: Shift, malam: Shift, libur: Shift}
     */
    private function makeShifts(Unit $unit): array
    {
        return [
            'pagi' => $this->createShift($unit, 'Pagi', 'PAGI'),
            'siang' => $this->createShift($unit, 'Siang', 'SIANG'),
            'malam' => $this->createShift($unit, 'Malam', 'MALAM', ['end_day_offset' => 1]),
            'libur' => $this->createShift($unit, 'Libur', 'LIBUR'),
        ];
    }

    /**
     * @param  array<int, Shift>  $shifts
     */
    private function makePattern(Unit $unit, array $shifts, string $name = 'Pola Rotasi'): SchedulePattern
    {
        $pattern = SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => $name,
            'cycle_length' => count($shifts),
            'is_active' => true,
        ]);

        foreach ($shifts as $index => $shift) {
            $pattern->addDetail($shift, $index + 1);
        }

        return $pattern;
    }

    private function createManualSchedule(
        SchedulePeriod $period,
        SchedulePeriodMember $member,
        Security $security,
        User $admin,
        Shift $shift,
        string $date,
        string $source = Schedule::SOURCE_ADJUSTMENT
    ): Schedule {
        return Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'schedule_pattern_id' => $member->schedule_pattern_id,
            'pattern_day_order' => 1,
            'date' => $date,
            'source' => $source,
            'created_by' => $admin->id,
        ]);
    }

    private function generate(
        SchedulePeriod $period,
        User $admin,
        string $mode = 'fill'
    ): TestResponse {
        return $this->actingAs($admin)->post(
            "/admin/schedule-period/{$period->id}/generate",
            ['mode' => $mode]
        );
    }
}
