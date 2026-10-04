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

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Resource $resource): bool
    {
        return $this->viewAny($user) && $user->canAccessTenant($resource->tenant_id);
    }

    public function delete(User $user, Resource $resource): bool
    {
        return $this->update($user, $resource);
    }
}
