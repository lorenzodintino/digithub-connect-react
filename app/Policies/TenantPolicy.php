<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Only platform administrators may browse every portal.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Administrators see any portal, members only their own.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->canAccessTenant($tenant);
    }

    /**
     * Portals are provisioned by the platform administrator only.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Administrators edit any portal, managers only their own branding.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin() || ($user->isManager() && $user->canAccessTenant($tenant));
    }

    /**
     * Portals are deleted by the platform administrator only.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin();
    }
}
