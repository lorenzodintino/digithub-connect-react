<?php

namespace App\Actions\Tenants;

use App\Enums\SubscriptionState;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Activity;
use Carbon\CarbonImmutable;

class SetPaidUntil
{
    /**
     * Record how long a portal has paid for, after a payment taken outside the platform.
     *
     * A new date starts the reminders over. A portal that was suspended because
     * it had not paid comes back on its own once the date covers today again;
     * one the administrator suspended by hand stays as it is.
     */
    public function handle(Tenant $tenant, ?CarbonImmutable $paidUntil, User $actor): Tenant
    {
        $previous = $tenant->paid_until?->toDateString();

        $tenant->forceFill([
            'paid_until' => $paidUntil?->toDateString(),
            'subscription_reminders_sent' => 0,
        ]);

        $reactivated = $tenant->subscription_suspended_at !== null
            && ! in_array($tenant->subscriptionState(), [SubscriptionState::Grace, SubscriptionState::Lapsed], true);

        if ($reactivated) {
            $tenant->forceFill(['is_active' => true, 'subscription_suspended_at' => null]);
        }

        $tenant->save();

        Activity::log('portal.paid_until', $tenant, [
            'from' => $previous,
            'to' => $tenant->paid_until?->toDateString(),
            'reactivated' => $reactivated,
        ], actor: $actor);

        return $tenant;
    }
}
