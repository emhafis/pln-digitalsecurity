<?php

namespace Database\Factories;

use App\Models\SchedulePattern;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchedulePattern>
 */
class SchedulePatternFactory extends Factory
{
    protected $model = SchedulePattern::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'name' => 'Pola '.fake()->unique()->word(),
            'description' => fake()->optional()->sentence(),
            'cycle_length' => 8,
            'is_active' => true,
        ];
    }
}
