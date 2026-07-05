<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hostel>
 */
class HostelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Hostel',
            'owner_name' => fake()->name(),
            'mobile' => (string) fake()->numerify('9#########'),
            'email' => fake()->unique()->safeEmail(),
            'city' => fake()->city(),
            'state' => 'Gujarat',
            'subscription_start' => now()->subMonth(),
            'subscription_end' => now()->addMonths(11),
            'status' => 'active',
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'subscription_end' => now()->subDay(),
        ]);
    }
}
