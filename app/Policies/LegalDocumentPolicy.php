<?php

namespace App\Policies;

use App\Models\LegalDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Impersonation;

class LegalDocumentPolicy
{
    /**
     * The platform documents and who accepted them are the administrator's to follow.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only the administrator publishes a new version.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * A portal is bound by the manager who runs it, and nobody else.
     *
     * Not even an administrator standing in through impersonation: the
     * platform cannot sign its own agreement on the customer's behalf.
     */
    public function accept(User $user, LegalDocument $document, Tenant $tenant): bool
    {
        return $document->type->requiresAcceptance()
            && $user->isManager()
            && $user->canAccessTenant($tenant)
            && ! app(Impersonation::class)->isActive();
    }
}
