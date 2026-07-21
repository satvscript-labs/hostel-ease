<?php

namespace Database\Factories;

use App\Enums\Presence\DeviceDirectionMode;
use App\Models\Hostel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PresenceDevice>
 */
class PresenceDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hostel_id' => Hostel::factory(),
            'serial_number' => 'TW'.fake()->unique()->numerify('###########'),
            'name' => fake()->randomElement(['Main Gate', 'Side Gate', 'Hostel Entry']),
            'direction_mode' => DeviceDirectionMode::Toggle,
            'is_active' => true,
        ];
    }

    public function toggle(): static
    {
        return $this->state(fn () => ['direction_mode' => DeviceDirectionMode::Toggle]);
    }

    public function entry(): static
    {
        return $this->state(fn () => ['direction_mode' => DeviceDirectionMode::Entry]);
    }

    public function exit(): static
    {
        return $this->state(fn () => ['direction_mode' => DeviceDirectionMode::Exit]);
    }
}
