<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'unit_name' => 'Unit '.fake()->unique()->numerify('######'),
            'unit_level' => fake()->randomElement(['uid', 'up', 'ulp']),
            'is_active' => true,
        ];
    }

    public function uid(): static
    {
        return $this->state(fn () => ['unit_level' => 'uid']);
    }

    public function up(): static
    {
        return $this->state(fn () => ['unit_level' => 'up']);
    }

    public function ulp(): static
    {
        return $this->state(fn () => ['unit_level' => 'ulp']);
    }
}
