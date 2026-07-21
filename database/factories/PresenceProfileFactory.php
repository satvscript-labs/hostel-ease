<?php

namespace Database\Factories;

use App\Enums\Presence\EnrollmentStatus;
use App\Enums\Presence\PresenceState;
use App\Models\Hostel;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PresenceProfile>
 */
class PresenceProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hostel_id' => Hostel::factory(),
            'presenceable_type' => Student::class,
            'presenceable_id' => Student::factory(),
            'device_user_id' => 'S'.fake()->unique()->numberBetween(1, 99999),
            'state' => PresenceState::Unknown,
            'enrollment_status' => EnrollmentStatus::Active,
            'enrolled_at' => now(),
        ];
    }

    /** Bind the profile to a concrete Student/Staff, inheriting its hostel. */
    public function forPerson($person): static
    {
        return $this->state(fn () => [
            'presenceable_type' => $person::class,
            'presenceable_id' => $person->id,
            'hostel_id' => $person->hostel_id,
        ]);
    }
}
