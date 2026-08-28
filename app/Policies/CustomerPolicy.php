<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * The anagraphic belongs to the portal, and the manager runs the portal.
     *
     * An operator sees the customers they are meeting through their own
     * agenda; the list of everybody the business ever served is not theirs.
     */
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    /**
     * A manager sees the customers of their own portal.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->isManager() && $user->canAccessTenant($customer->tenant_id);
    }

    /**
     * Only the notes the portal writes down are editable.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }
}
