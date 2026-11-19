<?php

namespace Tests\Feature\Scheduling;

use App\Models\Schedule;
use App\Models\SchedulePeriod;
use App\Models\SchedulePeriodMember;
use App\Models\Security;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ScheduleSourceNamingTest extends SchedulingTestCase
{
    public function test_source_constants_use_standardized_values(): void
    {
        $this->assertSame('system_generated', Schedule::SOURCE_SYSTEM_GENERATED);
        $this->assertSame('adjustment', Schedule::SOURCE_ADJUSTMENT);
    }

    public function test_source_column_defaults_to_system_generated_after_migration(): void
    {
        [$period, $member, $security, $shift, $admin] = $this->makeContext();

        $schedule = Schedule::create([
            'schedule_period_id' => $period->id,
            'schedule_period_member_id' => $member->id,
            'security_id' => $security->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-01',
            'created_by' => $admin->id,
        ]);

        $this->assertSame('system_generated', $schedule->fresh()->source);
    }

    public function test_source_column_rejects_legacy_rotation_and_manual_values(): void
    {
        [$period, $member, $security, $shift, $admin] = $this->makeContext();

        foreach (['rotation', 'manual'] as $legacy) {
            $rejected = false;

            try {
                DB::table('schedules')->insert([
                    'schedule_period_id' => $period->id,
                    'schedule_period_member_id' => $member->id,
                    'security_id' => $security->id,
                    'shift_id' => $shift->id,
                    'date' => '2026-11-01',
                    'source' => $legacy,
                    'created_by' => $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException) {
                $rejected = true;
            }

            $this->assertTrue(
                $rejected,
                "Nilai source lama [{$legacy}] seharusnya ditolak oleh schema."
            );
        }

        $this->assertDatabaseCount('schedules', 0);
    }

    /**
     * @return array{0: SchedulePeriod, 1: SchedulePeriodMember, 2: Security, 3: Shift, 4: User}
     */
    private function makeContext(): array
    {
        $unit = $this->createUnit();
        $post = $this->createPost($unit);
        $team = $this->createTeam($post);
        $admin = $this->createAdmin($unit);
        $security = $this->createSecurity($unit);
        $shift = $this->createShift($unit, 'Pagi', 'PAGI');
        $period = $this->createPeriod($unit, $post, $team, $admin, '2026-11-01', '2026-11-30');
        $member = $period->addMember($security);

        return [$period, $member, $security, $shift, $admin];
    }
}
