<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * The three plans the platform starts from, until the License Manager hands
     * over its own catalogue (docs/prompt-3-catalogo-piani.md).
     *
     * Services, operators, waiting-list places and packages are limits (a null
     * limit means no limit); the rest of what a plan includes is a set of
     * features (see App\Models\Plan::FEATURES).
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
                'code' => 'base',
                'name' => 'Base',
                'description' => 'Per chi inizia: un servizio alla volta e poche prenotazioni.',
                'price_cents' => 1900,
                'max_services' => 3,
                'max_operators' => 1,
                'max_bookings_per_month' => 100,
                'max_waiting_list' => null,
                'max_packages' => null,
                'features' => array_fill_keys(Plan::FEATURES, false),
                'position' => 1,
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'description' => 'Per uno studio con più persone e un giro di prenotazioni costante.',
                'price_cents' => 3900,
                'max_services' => 15,
                'max_operators' => 5,
                'max_bookings_per_month' => 1000,
                'max_waiting_list' => 5,
                'max_packages' => 3,
                'features' => [...array_fill_keys(Plan::FEATURES, true), 'group_services' => false],
                'position' => 2,
            ],
            [
                'code' => 'studio',
                'name' => 'Studio',
                'description' => 'Nessun limite, invio dal proprio server di posta.',
                'price_cents' => 7900,
                'max_services' => null,
                'max_operators' => null,
                'max_bookings_per_month' => null,
                'max_waiting_list' => null,
                'max_packages' => null,
                'features' => array_fill_keys(Plan::FEATURES, true),
                'position' => 3,
            ],
        ];
    }
}
