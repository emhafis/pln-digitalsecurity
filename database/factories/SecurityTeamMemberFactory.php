<?php

namespace Database\Factories;

use App\Models\Security;
use App\Models\SecurityTeam;
use App\Models\SecurityTeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityTeamMember>
 */
class SecurityTeamMemberFactory extends Factory
{
    protected $model = SecurityTeamMember::class;

    public function definition(): array
    {
        return [
            'security_team_id' => SecurityTeam::factory(),
            'security_id' => Security::factory(),
            'joined_at' => now()->startOfMonth()->toDateString(),
            'left_at' => null,
        ];
    }
}
