<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A complete, valid student — every MANDATORY field filled with a random but
 * realistic placeholder, so a fresh profile looks like a real one (P5 Aadhaar
 * included, encrypted by the model cast on save). Used by tests and by
 * `php artisan hostelease:make-students` for quick throwaway profiles.
 *
 * Identity only by default: no bed, no fee plan, no invoices — the freshest
 * useful state (assign a bed from the UI to raise the first invoice). Compose
 * with ->active() / states as needed.
 *
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $occupation = $this->faker->randomElement(['student', 'working']);

        return [
            'name' => $this->faker->name(),
            'mobile' => $this->mobile(),
            'father_mobile' => $this->mobile(),
            'mother_mobile' => $this->faker->boolean(70) ? $this->mobile() : null,
            // 12 digits, model encrypts it at rest (P5).
            'aadhaar' => (string) $this->faker->numerify('############'),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->randomElement(['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot', 'Gandhinagar']),
            'state' => 'Gujarat',
            'occupation_type' => $occupation,
            'college' => $occupation === 'student' ? $this->faker->randomElement(['Nirma University', 'GTU', 'DAIICT', 'LD College of Engineering']) : null,
            'field_of_study' => $occupation === 'student' ? $this->faker->randomElement(['Computer Engineering', 'Commerce', 'Mechanical', 'Design']) : null,
            'join_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'status' => 'active',
        ];
    }

    /** A genuine Indian-shape mobile: +91 then a 10-digit number starting 6–9. */
    protected function mobile(): string
    {
        return '+91'.$this->faker->numberBetween(6, 9).$this->faker->numerify('#########');
    }

    /** Give the student the sample Aadhaar card + photo (so Documents shows them). */
    public function withDocuments(string $aadhaarFile, ?string $photo = null): static
    {
        return $this->state(fn () => array_filter([
            'aadhaar_file' => $aadhaarFile,
            'photo' => $photo,
        ]));
    }

    /** A confirmed fee plan (still no bed — mirrors "plan saved, awaiting a bed"). */
    public function withPlan(float $amount = 6000, string $frequency = 'monthly'): static
    {
        return $this->state(fn () => [
            'fee_amount' => $amount,
            'fee_frequency' => $frequency,
            'room_preference' => $this->faker->randomElement(['AC', 'Non-AC']),
        ]);
    }

    public function left(): static
    {
        return $this->state(fn () => [
            'status' => 'left',
            'leave_date' => now()->subDays($this->faker->numberBetween(1, 60))->format('Y-m-d'),
        ]);
    }
}
