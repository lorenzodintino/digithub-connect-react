<?php

namespace App\Support;

use App\Models\Plan;

/**
 * The plans on sale as the public home page shows them. It reads the `plans`
 * table and nothing else, so the page, the back-office and the limits always
 * say the same thing; the table is the copy of what the License Manager defines.
 */
class PlanCatalogue
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function onSale(): array
    {
        $plans = Plan::query()->active()->orderBy('position')->orderBy('name')->get();

        return $plans->values()->map(fn (Plan $plan, int $index) => [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'price_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            // With three plans the middle one is the one the page pushes.
            'featured' => $plans->count() === 3 && $index === 1,
            'limits' => [
                'services' => $plan->max_services,
                'operators' => $plan->max_operators,
                'bookings' => $plan->max_bookings_per_month,
                'waiting_list' => $plan->max_waiting_list,
                'packages' => $plan->max_packages,
            ],
            'features' => $plan->featureMap(),
        ])->all();
    }
}
