<?php

namespace Database\Factories;

use App\Enums\BillingKind;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantBillingProfile>
 */
class TenantBillingProfileFactory extends Factory
{
    /**
     * A complete Italian business: the values pass the check digits.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'kind' => BillingKind::Company,
            'name' => 'Edil Rossi Srl',
            'first_name' => null,
            'last_name' => null,
            'contact_name' => 'Luca Bianchi',
            'tax_code' => '01234567897',
            'vat_number' => '01234567897',
            'sdi_code' => 'ABC1234',
            'pec' => null,
            'street' => 'Via Garibaldi 10',
            'postal_code' => '20121',
            'city' => 'Milano',
            'province' => 'MI',
            'country' => 'IT',
            'email' => 'info@edilrossi.example',
            'billing_email' => 'amministrazione@edilrossi.example',
            'phone' => null,
        ];
    }

    /**
     * A complete Italian private person.
     */
    public function person(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => BillingKind::Person,
            'name' => 'Mario Rossi',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'contact_name' => null,
            'tax_code' => 'RSSMRA85T10A562S',
            'vat_number' => null,
            'sdi_code' => null,
            'billing_email' => null,
            'email' => 'mario@example.it',
            'street' => 'Via Roma 1',
            'postal_code' => '30100',
            'city' => 'Venezia',
            'province' => 'VE',
        ]);
    }

    /**
     * A business abroad, with no Italian data.
     */
    public function foreign(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Muller GmbH',
            'tax_code' => null,
            'vat_number' => 'DE123456789',
            'sdi_code' => null,
            'street' => 'Unter den Linden 1',
            'postal_code' => '10117',
            'city' => 'Berlin',
            'province' => null,
            'country' => 'DE',
            'email' => 'info@muller.example',
            'billing_email' => null,
        ]);
    }

    /**
     * A profile with the kind chosen and little else: what is missing to invoice shows up.
     */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'vat_number' => null,
            'tax_code' => null,
            'sdi_code' => null,
            'street' => null,
            'postal_code' => null,
            'province' => null,
        ]);
    }
}
