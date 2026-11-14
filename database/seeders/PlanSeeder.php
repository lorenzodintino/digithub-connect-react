<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * The three plans the platform starts from.
     *
     * Services and operators are hard limits, the monthly bookings only warn
     * (see App\Services\PlanUsage); a null limit means no limit.
     *
     * Safe to run on a live database: a plan that already exists (same name)
     * is left exactly as it is, so prices and limits edited by the administrator survive.
     */
    public function run(): void
    {
        foreach ($this->plans() as $attributes) {
            Plan::firstOrCreate(
                ['name' => $attributes['name']],
                ['currency' => 'EUR', 'is_active' => true, ...$attributes],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plans(): array
    {
        return [
            [
                'name' => 'Base',
                'description' => 'Per chi inizia: un servizio alla volta e poche prenotazioni.',
                'price_cents' => 1900,
                'max_services' => 3,
                'max_operators' => 1,
                'max_bookings_per_month' => 100,
                'allows_custom_smtp' => false,
                'position' => 1,
            ],
            [
                'name' => 'Pro',
                'description' => 'Per uno studio con più persone e un giro di prenotazioni costante.',
                'price_cents' => 3900,
                'max_services' => 15,
                'max_operators' => 5,
                'max_bookings_per_month' => 1000,
                'allows_custom_smtp' => true,
                'position' => 2,
            ],
            [
                'name' => 'Studio',
                'description' => 'Nessun limite, invio dal proprio server di posta.',
                'price_cents' => 7900,
                'max_services' => null,
                'max_operators' => null,
                'max_bookings_per_month' => null,
                'allows_custom_smtp' => true,
                'position' => 3,
            ],
        ];
    }
}
