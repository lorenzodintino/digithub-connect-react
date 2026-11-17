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
            // The stable key the License Manager knows the plan by: the name, in lowercase.
            'code' => fn (array $attributes) => Str::slug($attributes['name']),
            'description' => fake()->sentence(),
            'price_cents' => fake()->randomElement([1900, 3900, 7900]),
            'currency' => 'EUR',
            'max_services' => 10,
            'max_operators' => 5,
            'max_bookings_per_month' => 500,
            'max_waiting_list' => null,
            'max_packages' => null,
            // Everything but the own mail server, which tests turn on when they need it.
            'features' => [...array_fill_keys(Plan::FEATURES, true), 'custom_smtp' => false],
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
            'features' => array_fill_keys(Plan::FEATURES, true),
        ]);
    }

    /**
     * Indicate that the plan does not include the given features.
     *
     * @param  list<string>  $features
     */
    public function lacking(array $features): static
    {
        return $this->state(fn (array $attributes) => [
            'features' => [...($attributes['features'] ?? []), ...array_fill_keys($features, false)],
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
