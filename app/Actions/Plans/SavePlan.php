<?php

namespace App\Actions\Plans;

use App\Models\Plan;
use App\Support\Activity;

class SavePlan
{
    /**
     * @param  array<string, mixed>  $attributes  validated by PlanRequest
     */
    public function create(array $attributes): Plan
    {
        $plan = Plan::create($this->planAttributes($attributes));

        Activity::log('plan.created', $plan, [], null);

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $attributes  validated by PlanRequest
     */
    public function update(Plan $plan, array $attributes): Plan
    {
        $plan->update($this->planAttributes($attributes));

        Activity::log('plan.updated', $plan, [], null);

        return $plan;
    }

    /**
     * The price is typed in euros and kept in cents; an empty limit is no limit.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function planAttributes(array $attributes): array
    {
        $limit = fn (string $key): ?int => ($attributes[$key] ?? null) === null || $attributes[$key] === '' ? null : (int) $attributes[$key];

        return [
            'name' => $attributes['name'],
            'description' => ($attributes['description'] ?? null) ?: null,
            'price_cents' => ($attributes['price'] ?? null) === null || $attributes['price'] === '' ? null : (int) round((float) $attributes['price'] * 100),
            'currency' => 'EUR',
            'max_services' => $limit('max_services'),
            'max_operators' => $limit('max_operators'),
            'max_bookings_per_month' => $limit('max_bookings_per_month'),
            'allows_custom_smtp' => (bool) $attributes['allows_custom_smtp'],
            'is_active' => (bool) $attributes['is_active'],
            'position' => (int) $attributes['position'],
        ];
    }
}
