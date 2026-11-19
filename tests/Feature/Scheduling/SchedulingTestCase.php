<?php

namespace Tests\Feature\Scheduling;

use App\Models\SchedulePattern;
use App\Models\SchedulePeriod;
use App\Models\Security;
use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class SchedulingTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createUnit(string $level = 'ulp', ?Unit $parent = null): Unit
    {
        return Unit::factory()->create([
            'unit_level' => $level,
            'parent_id' => $parent?->id,
        ]);
    }

    protected function createPost(Unit $unit): SecurityPost
    {
        return SecurityPost::factory()->create(['unit_id' => $unit->id]);
    }

    protected function createSecurity(Unit $unit, string $name = 'Satpam'): Security
    {
        return Security::factory()
            ->forUnit($unit)
            ->create(['full_name' => $name]);
    }

    protected function createAdmin(?Unit $unit = null): User
    {
        return User::factory()->create([
            'unit_id' => $unit?->id,
            'role' => 'admin',
        ]);
    }

    protected function createTeam(
        SecurityPost $post,
        string $slot = 'A'
    ): SecurityTeam {
        $slot = strtoupper($slot);
        $name = SecurityTeam::nameForCode($slot);

        if ($name === null) {
            throw new \InvalidArgumentException("Slot regu [{$slot}] tidak valid.");
        }

        return SecurityTeam::factory()->create([
            'security_post_id' => $post->id,
            'name' => $name,
            'code' => $slot,
        ]);
    }

    protected function createShift(
        Unit $unit,
        string $name,
        string $code,
        array $attributes = []
    ): Shift {
        return Shift::factory()->create(array_merge([
            'unit_id' => $unit->id,
            'name' => $name,
            'code' => $code,
        ], $attributes));
    }

    protected function createPattern(Unit $unit, string $name = 'Pola 8 Hari'): SchedulePattern
    {
        return SchedulePattern::factory()->create([
            'unit_id' => $unit->id,
            'name' => $name,
            'cycle_length' => 8,
        ]);
    }

    protected function createPeriod(
        Unit $unit,
        SecurityPost $post,
        SecurityTeam $team,
        User $creator,
        string $startDate,
        string $endDate,
        string $status = SchedulePeriod::STATUS_DRAFT,
        ?string $name = null
    ): SchedulePeriod {
        return SchedulePeriod::create([
            'unit_id' => $unit->id,
            'security_post_id' => $post->id,
            'security_team_id' => $team->id,
            'name' => $name ?? 'Periode '.$startDate,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
            'created_by' => $creator->id,
        ]);
    }
}
