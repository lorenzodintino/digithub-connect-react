<?php

namespace App\Models;

use App\Enums\BillingKind;
use Database\Factories\TenantBillingProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Who pays for a portal, with what an invoice needs.
 *
 * Personal data of the person or business that pays the platform: it is shown
 * to the administrator only, and it stays out of the portal export, the public
 * pages and the register. Values are normalised and checked by
 * App\Support\BillingData before they get here.
 *
 * @property int $id
 * @property int $tenant_id
 * @property BillingKind|null $kind
 * @property string|null $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $contact_name
 * @property string|null $tax_code
 * @property string|null $vat_number
 * @property string|null $sdi_code
 * @property string|null $pec
 * @property string|null $street
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $province
 * @property string|null $country
 * @property string|null $email
 * @property string|null $billing_email
 * @property string|null $phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'tenant_id', 'kind', 'name', 'first_name', 'last_name', 'contact_name',
    'tax_code', 'vat_number', 'sdi_code', 'pec',
    'street', 'postal_code', 'city', 'province', 'country',
    'email', 'billing_email', 'phone',
])]
class TenantBillingProfile extends Model
{
    /** @use HasFactory<TenantBillingProfileFactory> */
    use HasFactory;

    /**
     * The columns an administrator can fill in, in form order.
     *
     * @var array<int, string>
     */
    public const FIELDS = [
        'kind', 'name', 'first_name', 'last_name', 'contact_name',
        'tax_code', 'vat_number', 'sdi_code', 'pec',
        'street', 'postal_code', 'city', 'province', 'country',
        'email', 'billing_email', 'phone',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BillingKind::class,
        ];
    }

    /**
     * The portal this profile invoices.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
