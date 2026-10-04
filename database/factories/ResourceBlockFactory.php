<?php

namespace Database\Factories;

use App\Models\Resource;
use App\Models\ResourceBlock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResourceBlock> */
class ResourceBlockFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = CarbonImmutable::now('UTC')->addDay()->setTime(12, 0);

        return [
            'resource_id' => Resource::factory(),
            'tenant_id' => fn (array $attributes): int => Resource::query()->whereKey($attributes['resource_id'])->sole()->tenant_id,
            'starts_at' => $start,
            'ends_at' => $start->addHours(2),
            'reason' => 'Maintenance',
        ];
    }
}
