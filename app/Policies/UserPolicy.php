<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Administrators list every account, managers only their own staff.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Accounts are visible to administrators and to their own portal manager.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isAdmin()
            || $user->is($model)
            || ($user->isManager() && $user->canAccessTenant($model->tenant_id));
    }

    /**
     * Administrators create any account, managers only operators.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Managers may only touch operators of their own portal.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isManager()
            && $user->canAccessTenant($model->tenant_id)
            && $model->role === UserRole::Default;
    }

    /**
     * Nobody may delete their own account through the staff screens.
     */
    public function delete(User $user, User $model): bool
    {
        return ! $user->is($model) && $this->update($user, $model);
    }
}
