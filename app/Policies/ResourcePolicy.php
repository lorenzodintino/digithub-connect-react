<?php

namespace App\Policies;

use App\Models\Resource;
use App\Models\User;

class ResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager() && $user->belongsToTenant();
    }

    /**
     * Creating and changing resources is what the plan includes (or not). Looking,
     * deleting and marking unavailable stay open: they never add a promise to the agenda.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->tenant?->planAllows('resources') === true;
    }

    public function update(User $user, Resource $resource): bool
    {
        return $this->manage($user, $resource) && $resource->tenant->planAllows('resources');
    }

    public function delete(User $user, Resource $resource): bool
    {
        return $this->manage($user, $resource);
    }

    /**
     * Add or remove a period of unavailability.
     */
    public function block(User $user, Resource $resource): bool
    {
        return $this->manage($user, $resource);
    }

    private function manage(User $user, Resource $resource): bool
    {
        return $this->viewAny($user) && $user->canAccessTenant($resource->tenant_id);
    }
}
