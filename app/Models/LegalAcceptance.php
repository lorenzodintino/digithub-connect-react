<?php

namespace App\Models;

use Database\Factories\LegalAcceptanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Proof that a portal accepted one version of a platform document.
 *
 * The name of whoever accepted is copied, so the proof survives the account.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $legal_document_id
 * @property int|null $user_id
 * @property string $accepted_by_name
 * @property string|null $ip_address
 * @property Carbon $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tenant_id', 'legal_document_id', 'user_id', 'accepted_by_name', 'ip_address', 'accepted_at'])]
class LegalAcceptance extends Model
{
    /** @use HasFactory<LegalAcceptanceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * The portal bound by the acceptance.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The version that was accepted.
     *
     * @return BelongsTo<LegalDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }
}
