<?php

namespace Database\Factories;

use App\Models\AvailabilityRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityRule>
 */
class AvailabilityRuleFactory extends Factory
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
            'weekday' => fake()->numberBetween(1, 5),
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ];
    }

    /**
     * Indicate the weekday the rule applies to.
     */
    public function onWeekday(int $weekday): static
    {
        return $this->state(fn (array $attributes) => [
            'weekday' => $weekday,
        ]);
    }

    /**
     * Indicate the time window the rule covers.
     */
    public function between(string $startsAt, string $endsAt): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
