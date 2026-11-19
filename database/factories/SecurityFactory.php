<?php

namespace Database\Factories;

use App\Models\Security;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Security>
 */
class SecurityFactory extends Factory
{
    protected $model = Security::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'security']),
            'full_name' => fake()->name(),
            'phone_number' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'birth_place' => fake()->city(),
            'birth_date' => fake()->date(),
            'kta_issued_date' => now()->subYear()->toDateString(),
            'kta_expired_date' => now()->addYear()->toDateString(),
        ];
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory()->state([
                'role' => 'security',
                'unit_id' => $unit->id,
            ]),
        ]);
    }
}
