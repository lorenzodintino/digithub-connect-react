<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use App\Models\WaitingListEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitingListEntry>
 */
class WaitingListEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = CarbonImmutable::now()->startOfDay();

        return [
            'uid' => WaitingListEntry::newUid(),
            'tenant_id' => Tenant::factory(),
            'service_id' => Service::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->unique()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_timezone' => 'Europe/Rome',
            'customer_locale' => 'it',
            'wanted_from' => $from->toDateString(),
            'wanted_to' => $from->addDays(30)->toDateString(),
            'privacy_accepted_at' => now(),
            'privacy_accepted_url' => null,
            'notified_at' => null,
        ];
    }
}
