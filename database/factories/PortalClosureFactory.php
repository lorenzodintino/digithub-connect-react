<?php

namespace Database\Factories;

use App\Models\PortalClosure;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortalClosure>
 */
class PortalClosureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = CarbonImmutable::now()->addWeek()->startOfDay();

        return [
            'tenant_id' => Tenant::factory(),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $startsOn->addDays(2)->toDateString(),
            'reason' => 'Ferie',
        ];
    }
}
