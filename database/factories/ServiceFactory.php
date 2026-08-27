<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return [
            'tenant_id' => Tenant::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60]),
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => fake()->randomElement([0, 10, 15]),
            'slot_interval_minutes' => 15,
            'min_notice_minutes' => 120,
            'max_advance_days' => 60,
            'price_cents' => fake()->randomElement([null, 2500, 5000, 8000]),
            'currency' => 'EUR',
            'color' => fake()->hexColor(),
            'location_type' => fake()->randomElement(LocationType::cases()),
            'location_value' => fake()->sentence(3),
            'requires_confirmation' => false,
            'is_active' => true,
            'position' => 0,
        ];
    }

    /**
     * Indicate that the service is hidden from the public booking page.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that bookings must be approved by the portal.
     */
    public function requiringConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_confirmation' => true,
        ]);
    }
}
