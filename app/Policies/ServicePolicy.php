<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    /**
     * Every authenticated portal member may list the portal services.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->belongsToTenant();
    }

    /**
     * Services are visible to the members of the owning portal.
     */
    public function view(User $user, Service $service): bool
    {
        return $user->canAccessTenant($service->tenant_id);
    }

    /**
     * Only managers configure the catalogue of their own portal.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Only managers of the owning portal may edit a service.
     */
    public function update(User $user, Service $service): bool
    {
        return ($user->isAdmin() || $user->isManager()) && $user->canAccessTenant($service->tenant_id);
    }

    /**
     * Only managers of the owning portal may delete a service.
     */
    public function delete(User $user, Service $service): bool
    {
        return $this->update($user, $service);
    }
}
