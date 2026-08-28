<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->word()),
            'description' => fake()->sentence(),
            'price_cents' => fake()->randomElement([1900, 3900, 7900]),
            'currency' => 'EUR',
            'max_services' => 10,
            'max_operators' => 5,
            'max_bookings_per_month' => 500,
            'allows_custom_smtp' => false,
            'is_active' => true,
            'position' => 0,
        ];
    }

    /**
     * Indicate that the plan carries no limit at all.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_services' => null,
            'max_operators' => null,
            'max_bookings_per_month' => null,
            'allows_custom_smtp' => true,
        ]);
    }

    /**
     * Indicate that the plan is no longer sold.
     */
    public function retired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
