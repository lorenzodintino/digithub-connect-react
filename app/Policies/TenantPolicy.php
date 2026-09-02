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
     * Email delivery is set up by the platform, not by the portal.
     *
     * The portal's own SMTP server is an option offered on request and
     * configured once during onboarding: the manager sees whether it works and
     * can test it, but the credentials of a mail server are not something to
     * hand to whoever happens to run the reception desk.
     */
    public function configureMailer(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin();
    }

    /**
     * Portals are deleted by the platform administrator only.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isAdmin();
    }
}
