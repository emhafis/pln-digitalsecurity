<?php

namespace Database\Factories;

use App\Models\SecurityPost;
use App\Models\SecurityTeam;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * @extends Factory<SecurityTeam>
 */
class SecurityTeamFactory extends Factory
{
    protected $model = SecurityTeam::class;

    public function definition(): array
    {
        return [
            'security_post_id' => SecurityPost::factory(),
            'name' => SecurityTeam::NAMES['A'],
            'code' => 'A',
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SecurityTeam $team): void {
            if ($team->security_post_id === null) {
                return;
            }

            $usedNames = SecurityTeam::query()
                ->where('security_post_id', $team->security_post_id)
                ->when($team->exists, fn ($query) => $query->whereKeyNot($team->getKey()))
                ->pluck('name')
                ->all();

            $name = $team->name;

            if (
                $name === null
                || ! in_array($name, SecurityTeam::NAMES, true)
                || in_array($name, $usedNames, true)
            ) {
                $name = $this->availableName($usedNames);
                $team->name = $name;
            }

            $team->code = SecurityTeam::codeForName($name);
        });
    }

    /**
     * @param  array<int, string>  $usedNames
     */
    private function availableName(array $usedNames): string
    {
        $available = array_values(array_diff(array_values(SecurityTeam::NAMES), $usedNames));

        if ($available === []) {
            throw new RuntimeException('Tidak ada slot regu tersisa untuk pos jaga ini.');
        }

        return $available[0];
    }
}
