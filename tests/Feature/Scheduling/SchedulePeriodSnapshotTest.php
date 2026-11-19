<?php

namespace Tests\Feature\Scheduling;

use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\SecurityTeamMember;

class SchedulePeriodSnapshotTest extends SchedulingTestCase
{
    public function test_period_members_are_snapshot_independent_from_team_membership(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);

        $a = $this->createSecurity($unit, 'Satpam A');
        $b = $this->createSecurity($unit, 'Satpam B');
        $c = $this->createSecurity($unit, 'Satpam C');
        $d = $this->createSecurity($unit, 'Satpam D');

        $team->assignMember($a, '2026-11-01');
        $team->assignMember($b, '2026-11-01');
        $team->assignMember($c, '2026-11-01');

        $november = $this->createPeriod(
            $unit,
            $post,
            $team,
            $admin,
            '2026-11-01',
            '2026-11-30',
            SchedulePeriod::STATUS_ACTIVE,
            'November 2026'
        );

        $november->addMember($a);
        $novemberMemberB = $november->addMember($b);
        $november->addMember($c);

        $december = $this->createPeriod(
            $unit,
            $post,
            $team,
            $admin,
            '2026-12-01',
            '2026-12-31',
            SchedulePeriod::STATUS_DRAFT,
            'Desember 2026'
        );

        SecurityTeamMember::query()
            ->where('security_team_id', $team->id)
            ->where('security_id', $b->id)
            ->firstOrFail()
            ->update(['left_at' => '2026-11-30']);

        $team->assignMember($d, '2026-12-01');

        $december->addMember($a);
        $december->addMember($d);

        $this->assertSame(
            [$a->id, $b->id, $c->id],
            $november->members()->orderBy('security_id')->pluck('security_id')->all()
        );
        $this->assertSame(
            [$a->id, $d->id],
            $december->members()->orderBy('security_id')->pluck('security_id')->all()
        );

        $shift = $this->createShift($unit, 'Pagi', 'PAGI');
        $pattern = $this->createPattern($unit);
        $pattern->addDetail($shift, 1);

        $novemberSchedule = Schedule::create([
            'schedule_period_id' => $november->id,
            'schedule_period_member_id' => $novemberMemberB->id,
            'security_id' => $b->id,
            'shift_id' => $shift->id,
            'schedule_pattern_id' => $pattern->id,
            'pattern_day_order' => 1,
            'date' => '2026-11-15',
            'source' => Schedule::SOURCE_SYSTEM_GENERATED,
            'created_by' => $admin->id,
        ]);

        SecurityTeamMember::query()
            ->where('security_team_id', $team->id)
            ->where('security_id', $d->id)
            ->firstOrFail()
            ->update(['left_at' => '2026-12-15']);

        $team->assignMember($b, '2026-12-16');

        $this->assertSame(
            [$a->id, $b->id, $c->id],
            $november->fresh()->members()->orderBy('security_id')->pluck('security_id')->all()
        );
        $this->assertSame(
            [$a->id, $d->id],
            $december->fresh()->members()->orderBy('security_id')->pluck('security_id')->all()
        );

        $novemberSchedule->refresh();

        $this->assertSame($b->id, $novemberSchedule->security_id);
        $this->assertSame($shift->id, $novemberSchedule->shift_id);
        $this->assertSame(1, $novemberSchedule->pattern_day_order);
    }

    public function test_copy_members_from_previous_period_creates_separate_snapshot_rows(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);

        $a = $this->createSecurity($unit, 'Satpam A');
        $b = $this->createSecurity($unit, 'Satpam B');

        $team->assignMember($a, '2026-11-01');
        $team->assignMember($b, '2026-11-01');

        $november = $this->createPeriod(
            $unit,
            $post,
            $team,
            $admin,
            '2026-11-01',
            '2026-11-30',
            SchedulePeriod::STATUS_COMPLETED,
            'November 2026'
        );

        $november->addMember($a);
        $november->addMember($b);

        $december = $this->createPeriod(
            $unit,
            $post,
            $team,
            $admin,
            '2026-12-01',
            '2026-12-31',
            SchedulePeriod::STATUS_DRAFT,
            'Desember 2026'
        );

        $copied = $december->copyMembersFrom($november);

        $this->assertSame(2, $copied);
        $this->assertSame(
            [$a->id, $b->id],
            $december->members()->orderBy('security_id')->pluck('security_id')->all()
        );
        $this->assertSame(
            0,
            $december->members()
                ->whereIn('id', $november->members()->pluck('id'))
                ->count()
        );

        SecurityTeamMember::query()
            ->where('security_team_id', $team->id)
            ->where('security_id', $a->id)
            ->firstOrFail()
            ->update(['left_at' => '2026-11-15']);

        $this->assertSame(
            [$a->id, $b->id],
            $december->fresh()->members()->orderBy('security_id')->pluck('security_id')->all()
        );
        $this->assertSame(
            [$a->id, $b->id],
            $november->fresh()->members()->orderBy('security_id')->pluck('security_id')->all()
        );
    }

    public function test_saved_schedule_is_not_recalculated_when_pattern_master_changes(): void
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit, 'Satpam A');

        $team->assignMember($security, '2026-11-01');

        $period = $this->createPeriod(
            $unit,
            $post,
            $team,
            $admin,
            '2026-11-01',
            '2026-11-30',
            SchedulePeriod::STATUS_ACTIVE,
            'November 2026'
        );

        $pagi = $this->createShift($unit, 'Pagi', 'PAGI');
        $siang = $this->createShift($unit, 'Siang', 'SIANG');
        $pattern = $this->createPattern($unit);
        $detail = $pattern->addDetail($pagi, 1);

        $member = $period->addMember($security, $pattern);

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

        $detail->update(['shift_id' => $siang->id]);
        $pattern->addDetail($siang, 2);

        $schedule->refresh();

        $this->assertSame($pagi->id, $schedule->shift_id);
        $this->assertSame(1, $schedule->pattern_day_order);
        $this->assertSame(Schedule::SOURCE_SYSTEM_GENERATED, $schedule->source);
    }
}
