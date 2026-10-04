<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceResourceRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceResourceRequirement> */
class ServiceResourceRequirementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'tenant_id' => fn (array $attributes): int => Service::query()->whereKey($attributes['service_id'])->sole()->tenant_id,
            'name' => 'Treatment room',
            'position' => 0,
        ];
    }
}
