<?php

namespace Database\Factories;

use App\Models\AvailabilityException;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityException>
 */
class AvailabilityExceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'is_unavailable' => true,
            'starts_at' => null,
            'ends_at' => null,
            'reason' => fake()->randomElement([null, 'Ferie', 'Formazione', 'Chiusura']),
        ];
    }

    /**
     * Indicate that the day uses custom hours instead of being closed.
     */
    public function customHours(string $startsAt = '10:00', string $endsAt = '14:00'): static
    {
        return $this->state(fn (array $attributes) => [
            'is_unavailable' => false,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
