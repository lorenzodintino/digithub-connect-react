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
        $plan = Plan::create([...$this->planAttributes($attributes), 'features' => $this->features($attributes, [])]);

        Activity::log('plan.created', $plan, [], null);

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $attributes  validated by PlanRequest
     */
    public function update(Plan $plan, array $attributes): Plan
    {
        $plan->update([...$this->planAttributes($attributes), 'features' => $this->features($attributes, $plan->features ?? [])]);

        Activity::log('plan.updated', $plan, [], null);

        return $plan;
    }

    /**
     * Every feature as a yes or a no: what the form sends wins, what it leaves out keeps
     * the current answer (a new plan starts with none).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, bool>  $current
     * @return array<string, bool>
     */
    private function features(array $attributes, array $current): array
    {
        $sent = is_array($attributes['features'] ?? null) ? $attributes['features'] : [];

        return collect(Plan::FEATURES)->mapWithKeys(fn (string $feature) => [$feature => (bool) ($sent[$feature] ?? $current[$feature] ?? false)])->all();
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
            'max_waiting_list' => $limit('max_waiting_list'),
            'max_packages' => $limit('max_packages'),
            'is_active' => (bool) $attributes['is_active'],
            'position' => (int) $attributes['position'],
        ];
    }
}
