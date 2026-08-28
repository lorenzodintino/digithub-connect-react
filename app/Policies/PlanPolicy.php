<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    /**
     * Plans are the platform's price list: only its administrator sees them.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Same rule for a single plan.
     */
    public function view(User $user, Plan $plan): bool
    {
        return $user->isAdmin();
    }

    /**
     * Creating a plan is a commercial decision of the platform.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Editing a plan changes what every portal on it is entitled to.
     */
    public function update(User $user, Plan $plan): bool
    {
        return $user->isAdmin();
    }

    /**
     * A plan still carrying portals must not disappear under their feet.
     */
    public function delete(User $user, Plan $plan): bool
    {
        return $user->isAdmin() && $plan->tenants()->doesntExist();
    }
}
