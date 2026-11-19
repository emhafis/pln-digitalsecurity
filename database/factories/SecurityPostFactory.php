<?php

namespace Database\Factories;

use App\Models\SecurityPost;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityPost>
 */
class SecurityPostFactory extends Factory
{
    protected $model = SecurityPost::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'name' => 'Pos '.fake()->unique()->numerify('####'),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ];
    }
}
