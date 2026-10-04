<?php

namespace Database\Factories;

use App\Enums\ResourceType;
use App\Models\Resource;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<resource> */
class ResourceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->bothify('Resource ##????'),
            'type' => ResourceType::Room,
            'description' => null,
            'notes' => null,
            'preparation_minutes' => 0,
            'cleanup_minutes' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
