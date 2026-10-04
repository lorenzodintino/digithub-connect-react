<?php

namespace App\Actions\Legal;

use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Activity;

class AcceptLegalDocument
{
    /**
     * Record that a portal accepted the version of a document now in force.
     *
     * Accepting twice keeps the first acceptance: that is the moment the portal
     * became bound, and a later click does not move it.
     */
    public function handle(LegalDocument $document, Tenant $tenant, User $user, ?string $ipAddress): LegalAcceptance
    {
        abort_unless(LegalDocument::current()->get($document->type->value)?->is($document), 409);

        $acceptance = LegalAcceptance::query()->firstOrCreate(
            ['tenant_id' => $tenant->getKey(), 'legal_document_id' => $document->getKey()],
            ['user_id' => $user->getKey(), 'accepted_by_name' => $user->name, 'ip_address' => $ipAddress, 'accepted_at' => now()],
        );

        if ($acceptance->wasRecentlyCreated) {
            Activity::log('legal.accepted', $document, ['type' => $document->type->value, 'version' => $document->version], tenant: $tenant, label: $document->type->label().' '.$document->version, actor: $user);
        }

        return $acceptance;
    }
}
