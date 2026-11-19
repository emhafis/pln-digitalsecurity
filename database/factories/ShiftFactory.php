<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'name' => 'Shift '.fake()->unique()->numerify('####'),
            'code' => Str::upper(Str::random(5)),
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'end_day_offset' => 0,
            'color' => fake()->safeHexColor(),
            'is_active' => true,
        ];
    }

    public function night(): static
    {
        return $this->state(fn () => [
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'end_day_offset' => 1,
        ]);
    }
}
