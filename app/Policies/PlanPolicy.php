<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

/**
 * Plans are defined in the License Manager: here they can only be read. There is deliberately
 * no create, update or delete: nothing in this application writes a plan but `plans:sync`.
 */
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
}
