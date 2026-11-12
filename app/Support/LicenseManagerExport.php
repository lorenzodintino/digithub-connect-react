<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use Carbon\CarbonImmutable;

/**
 * The registration file of a portal for DigitHub License Manager
 * (docs/struttura-json-registrazione-cliente.md): one JSON per portal, the
 * names and the rules of the License Manager, the same today (exported by
 * hand and imported from its panel) and tomorrow (the body of an automatic
 * registration call).
 *
 * Nothing else goes in it: no key, no licence data, no manager and none of
 * the portal's own customers.
 */
final class LicenseManagerExport
{
    /**
     * Changes only if the structure changes in an incompatible way.
     */
    public const SCHEMA_VERSION = 1;

    /**
     * @return array{schemaVersion: int, exportedAt: string, software: string, tenant: array{externalId: string}, customer: array<string, string|null>}
     */
    public static function payload(Tenant $tenant, TenantBillingProfile $profile): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'exportedAt' => CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z'),
            // The software code of this environment, from the configuration only.
            'software' => (string) config('services.license.software'),
            'tenant' => ['externalId' => (string) $tenant->license_id],
            // Every field is always there, `null` when there is nothing: never an empty text.
            'customer' => [
                'kind' => $profile->kind?->value,
                'name' => self::value($profile->name),
                'firstName' => self::value($profile->first_name),
                'lastName' => self::value($profile->last_name),
                'contactName' => self::value($profile->contact_name),
                'taxCode' => self::value($profile->tax_code),
                'vatNumber' => self::value($profile->vat_number),
                'sdiCode' => self::value($profile->sdi_code),
                'pec' => self::value($profile->pec),
                'street' => self::value($profile->street),
                'postalCode' => self::value($profile->postal_code),
                'city' => self::value($profile->city),
                'province' => self::value($profile->province),
                'country' => self::value($profile->country) ?? 'IT',
                'email' => self::value($profile->email),
                'billingEmail' => self::value($profile->billing_email),
                'phone' => self::value($profile->phone),
            ],
        ];
    }

    private static function value(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
