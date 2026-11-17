<?php

namespace App\Actions\Tenants;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BillingData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SavePortal
{
    public function __construct(private readonly SaveBillingProfile $saveBillingProfile) {}

    /**
     * Provision a portal together with its single manager account, whose
     * password is generated here and shown to the administrator only once.
     *
     * @param  array<string, mixed>  $attributes  validated by PortalRequest
     * @return array{portal: Tenant, manager: User, password: string}
     */
    public function create(array $attributes): array
    {
        $password = Str::password(14, symbols: false);

        return DB::transaction(function () use ($attributes, $password): array {
            $portal = Tenant::create($this->portalAttributes($attributes));

            $this->saveBilling($portal, $attributes);

            $manager = User::create([
                'tenant_id' => $portal->id,
                'name' => $attributes['manager_name'],
                'email' => $attributes['manager_email'],
                'password' => $password,
                'role' => UserRole::Manager,
                'timezone' => $portal->timezone,
                'locale' => $portal->locale,
                'is_bookable' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            return ['portal' => $portal, 'manager' => $manager, 'password' => $password];
        });
    }

    /**
     * Change the details of a portal; its manager account is left alone.
     *
     * @param  array<string, mixed>  $attributes  validated by PortalRequest
     */
    public function update(Tenant $portal, array $attributes): Tenant
    {
        DB::transaction(function () use ($portal, $attributes): void {
            $portalAttributes = $this->portalAttributes($attributes);

            $portal->update($portalAttributes);

            $this->saveBilling($portal, $attributes);
        });

        return $portal;
    }

    /**
     * Who pays for the portal goes in with the portal. A portal from before
     * the billing profile existed that is saved with the section left empty
     * stays without one, and shows up as incomplete.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function saveBilling(Tenant $portal, array $attributes): void
    {
        /** @var array<string, string|null> $billing */
        $billing = $attributes['billing'] ?? [];

        if ($portal->billingProfile === null && BillingData::isEmpty($billing)) {
            return;
        }

        $this->saveBillingProfile->handle($portal, $billing);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function portalAttributes(array $attributes): array
    {
        return [
            'name' => $attributes['name'],
            'slug' => $attributes['slug'],
            'timezone' => $attributes['timezone'],
            'locale' => $attributes['locale'],
            'brand_color' => $attributes['brand_color'],
            'description' => ($attributes['description'] ?? null) ?: null,
            'contact_email' => ($attributes['contact_email'] ?? null) ?: null,
            'contact_phone' => ($attributes['contact_phone'] ?? null) ?: null,
            'website' => ($attributes['website'] ?? null) ?: null,
            'address' => ($attributes['address'] ?? null) ?: null,
            'is_active' => (bool) $attributes['is_active'],
        ];
    }
}
