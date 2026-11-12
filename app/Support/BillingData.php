<?php

namespace App\Support;

use App\Enums\BillingKind;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use Closure;

/**
 * The billing profile of a portal: how the form's input is cleaned, which
 * rules a complete profile must meet, what is still missing from a saved one,
 * and the text the administrator copies into DigitHub License Manager.
 *
 * It follows the rules of the License Manager (docs/riferimento/
 * customer-profile.ts) so the same input gives the same result in both. Only
 * format and check digits are verified, never online.
 *
 * Form fields use snake_case names; the business name travels as
 * `company_name` and is stored in `name`, the display name of either kind.
 */
final class BillingData
{
    /**
     * The fields of the form, in order.
     *
     * @var array<int, string>
     */
    public const FORM_FIELDS = [
        'kind', 'company_name', 'first_name', 'last_name', 'contact_name',
        'tax_code', 'vat_number', 'sdi_code', 'pec',
        'street', 'postal_code', 'city', 'province', 'country',
        'email', 'billing_email', 'phone',
    ];

    /**
     * Clean what the form sent.
     *
     * Codes lose spaces and go uppercase, emails go lowercase, text loses extra
     * spaces; the fields that belong to the other kind are dropped; the
     * country defaults to Italy and Italian tax data lose the IT prefix.
     * Nothing is refused here: rules() judges the result.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>
     */
    public static function normalise(array $input): array
    {
        $kind = self::text($input['kind'] ?? null);
        $isCompany = $kind === BillingKind::Company->value;
        $isPerson = $kind === BillingKind::Person->value;
        $country = self::code($input['country'] ?? null) ?? 'IT';
        $italian = $country === 'IT';

        $vatNumber = $isPerson ? null : self::code($input['vat_number'] ?? null);
        $taxCode = self::code($input['tax_code'] ?? null);

        if ($italian && $vatNumber !== null && str_starts_with($vatNumber, 'IT')) {
            $vatNumber = substr($vatNumber, 2);
        }

        // A business often writes its VAT number, prefix included, as tax code;
        // a personal tax code never looks like that.
        if ($italian && $isCompany && $taxCode !== null && preg_match('/^IT\d{11}$/', $taxCode) === 1) {
            $taxCode = substr($taxCode, 2);
        }

        return [
            'kind' => $kind,
            'company_name' => $isPerson ? null : self::text($input['company_name'] ?? null),
            'first_name' => $isCompany ? null : self::text($input['first_name'] ?? null),
            'last_name' => $isCompany ? null : self::text($input['last_name'] ?? null),
            'contact_name' => $isPerson ? null : self::text($input['contact_name'] ?? null),
            'tax_code' => $taxCode,
            'vat_number' => $vatNumber,
            'sdi_code' => $isPerson ? null : self::code($input['sdi_code'] ?? null),
            'pec' => $isPerson ? null : self::email($input['pec'] ?? null),
            'street' => self::text($input['street'] ?? null),
            // Italian postcodes and provinces are compact; foreign ones keep
            // their spaces (SW1A 1AA) and, for the region, their case.
            'postal_code' => $italian ? self::code($input['postal_code'] ?? null) : self::upper(self::text($input['postal_code'] ?? null)),
            'city' => self::text($input['city'] ?? null),
            'province' => $italian ? self::code($input['province'] ?? null) : self::text($input['province'] ?? null),
            'country' => $country,
            'email' => self::email($input['email'] ?? null),
            'billing_email' => self::email($input['billing_email'] ?? null),
            'phone' => self::text($input['phone'] ?? null),
        ];
    }

    /**
     * Whether nothing was filled in. The country alone does not count: the
     * form starts on Italy.
     *
     * @param  array<string, mixed>  $billing  normalised
     */
    public static function isEmpty(array $billing): bool
    {
        foreach ($billing as $field => $value) {
            if ($field !== 'country' && $value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * What a complete profile needs, for the kind and country chosen.
     *
     * Italian private: first and last name, tax code, full address, email.
     * Italian business: business name, VAT number, SDI code or PEC, full
     * address, email. Abroad: no Italian check; name, street, city and email,
     * and for a business the VAT number or its equivalent.
     *
     * @param  array<string, mixed>  $billing  normalised
     * @return array<string, array<int, mixed>>
     */
    public static function rules(array $billing): array
    {
        $kind = $billing['kind'] ?? null;
        $isCompany = $kind === BillingKind::Company->value;
        $isPerson = $kind === BillingKind::Person->value;
        $italian = ($billing['country'] ?? 'IT') === 'IT';

        $text = fn (int $max): array => ['string', 'max:'.$max, self::noControlCharacters()];

        $rules = [
            'billing' => ['required', 'array'],
            'billing.kind' => ['required', self::check(__('Choose whether the payer is a private person or a business.'), fn (string $value): bool => BillingKind::tryFrom($value) !== null)],
            'billing.country' => ['required', self::check(__('Country: use the two-letter code, for example IT, DE, FR.'), fn (string $value): bool => preg_match('/^[A-Z]{2}$/', $value) === 1)],
            'billing.street' => ['required', ...$text(200)],
            'billing.city' => ['required', ...$text(100)],
            'billing.postal_code' => $italian
                ? ['required', self::check(__('Postal code not valid: it has 5 digits.'), fn (string $value): bool => preg_match('/^\d{5}$/', $value) === 1)]
                : ['nullable', ...$text(12)],
            'billing.province' => $italian
                ? ['required', self::check(__('Province: use the two-letter code, for example MI.'), fn (string $value): bool => preg_match('/^[A-Z]{2}$/', $value) === 1)]
                : ['nullable', ...$text(40)],
            'billing.email' => ['required', 'email', 'max:254'],
            'billing.billing_email' => ['nullable', 'email', 'max:254'],
            'billing.phone' => ['nullable', ...$text(40)],
        ];

        if ($isPerson) {
            $firstName = (string) ($billing['first_name'] ?? '');

            $rules['billing.first_name'] = ['required', ...$text(60)];
            // The display name is stored in one column of 120 characters: refuse rather than cut it.
            $rules['billing.last_name'] = ['required', ...$text(60), self::check(__('First and last name together: at most 120 characters.'), fn (string $value): bool => mb_strlen($firstName.' '.$value) <= 120)];
            $rules['billing.tax_code'] = $italian
                ? ['required', ...$text(30), self::check(__('Tax code not valid: check the 16 characters.'), ItalianTaxId::isValidTaxCode(...))]
                : ['nullable', ...$text(30)];
        }

        if ($isCompany) {
            $rules['billing.company_name'] = ['required', 'string', 'min:2', 'max:120', self::noControlCharacters()];
            $rules['billing.contact_name'] = ['nullable', ...$text(120)];
            $rules['billing.vat_number'] = $italian
                ? ['required', ...$text(30), self::check(__('VAT number not valid: it has 11 digits with the correct check digit.'), ItalianTaxId::isValidVatNumber(...))]
                : ['required', ...$text(30)];
            $rules['billing.tax_code'] = $italian
                ? ['nullable', ...$text(30), self::check(__('Tax code not valid: 11 digits like the VAT number, or 16 characters for a sole proprietorship.'), fn (string $value): bool => ItalianTaxId::isValidVatNumber($value) || ItalianTaxId::isValidTaxCode($value))]
                : ['nullable', ...$text(30)];
            $rules['billing.sdi_code'] = [
                ...($italian ? ['required_without:billing.pec'] : ['nullable']),
                self::check(__('SDI code not valid: it has 7 letters or digits.'), fn (string $value): bool => preg_match('/^[A-Z0-9]{7}$/', $value) === 1),
            ];
            $rules['billing.pec'] = ['nullable', 'email', 'max:254'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'billing.kind.required' => __('Choose whether the payer is a private person or a business.'),
            'billing.sdi_code.required_without' => __('Enter the SDI code or the PEC.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'billing.kind' => __('billing type'),
            'billing.company_name' => __('business name'),
            'billing.first_name' => __('first name'),
            'billing.last_name' => __('last name'),
            'billing.contact_name' => __('contact person'),
            'billing.tax_code' => __('tax code'),
            'billing.vat_number' => __('vat number'),
            'billing.sdi_code' => __('sdi code'),
            'billing.pec' => __('PEC'),
            'billing.street' => __('street address'),
            'billing.postal_code' => __('postal code'),
            'billing.city' => __('city'),
            'billing.province' => __('province'),
            'billing.country' => __('country'),
            'billing.email' => __('email'),
            'billing.billing_email' => __('email for invoices and notices'),
            'billing.phone' => __('phone'),
        ];
    }

    /**
     * The columns of the profile for what the form sent, once validated.
     *
     * @param  array<string, mixed>  $billing  normalised and validated
     * @return array<string, string|null>
     */
    public static function columns(array $billing): array
    {
        $isCompany = ($billing['kind'] ?? null) === BillingKind::Company->value;
        $first = $billing['first_name'] ?? null;
        $last = $billing['last_name'] ?? null;
        $personName = trim(($first ?? '').' '.($last ?? ''));

        return [
            'kind' => $billing['kind'] ?? null,
            'name' => $isCompany ? ($billing['company_name'] ?? null) : ($personName === '' ? null : $personName),
            'first_name' => $first,
            'last_name' => $last,
            'contact_name' => $billing['contact_name'] ?? null,
            'tax_code' => $billing['tax_code'] ?? null,
            'vat_number' => $billing['vat_number'] ?? null,
            'sdi_code' => $billing['sdi_code'] ?? null,
            'pec' => $billing['pec'] ?? null,
            'street' => $billing['street'] ?? null,
            'postal_code' => $billing['postal_code'] ?? null,
            'city' => $billing['city'] ?? null,
            'province' => $billing['province'] ?? null,
            'country' => $billing['country'] ?? null,
            'email' => $billing['email'] ?? null,
            'billing_email' => $billing['billing_email'] ?? null,
            'phone' => $billing['phone'] ?? null,
        ];
    }

    /**
     * The profile as the form shows it; a portal without one gets a blank form on Italy.
     *
     * @return array<string, string>
     */
    public static function formValues(?TenantBillingProfile $profile): array
    {
        return [
            'kind' => $profile->kind->value ?? '',
            'company_name' => $profile?->kind === BillingKind::Company ? (string) $profile->name : '',
            'first_name' => $profile->first_name ?? '',
            'last_name' => $profile->last_name ?? '',
            'contact_name' => $profile->contact_name ?? '',
            'tax_code' => $profile->tax_code ?? '',
            'vat_number' => $profile->vat_number ?? '',
            'sdi_code' => $profile->sdi_code ?? '',
            'pec' => $profile->pec ?? '',
            'street' => $profile->street ?? '',
            'postal_code' => $profile->postal_code ?? '',
            'city' => $profile->city ?? '',
            'province' => $profile->province ?? '',
            'country' => $profile->country ?? 'IT',
            'email' => $profile->email ?? '',
            'billing_email' => $profile->billing_email ?? '',
            'phone' => $profile->phone ?? '',
        ];
    }

    /**
     * What is still missing to invoice the portal, in words; empty when the
     * profile is complete. Saving an existing portal never depends on it.
     *
     * @return array<int, string>
     */
    public static function gaps(?TenantBillingProfile $profile): array
    {
        if ($profile === null || $profile->kind === null) {
            return [__('billing type')];
        }

        $italian = ($profile->country ?? 'IT') === 'IT';
        $gaps = [];

        if ($profile->kind === BillingKind::Company) {
            if (blank($profile->name)) {
                $gaps[] = __('business name');
            }

            if (blank($profile->vat_number)) {
                $gaps[] = __('vat number');
            }

            if ($italian && blank($profile->sdi_code) && blank($profile->pec)) {
                $gaps[] = __('SDI code or PEC');
            }
        } else {
            if (blank($profile->first_name) || blank($profile->last_name)) {
                $gaps[] = __('first and last name');
            }

            if ($italian && blank($profile->tax_code)) {
                $gaps[] = __('tax code');
            }
        }

        if (blank($profile->street)) {
            $gaps[] = __('street address');
        }

        if ($italian && blank($profile->postal_code)) {
            $gaps[] = __('postal code');
        }

        if (blank($profile->city)) {
            $gaps[] = __('city');
        }

        if ($italian && blank($profile->province)) {
            $gaps[] = __('province');
        }

        if (blank($profile->email)) {
            $gaps[] = __('email');
        }

        return $gaps;
    }

    /**
     * The block to paste into DigitHub License Manager, empty lines left out.
     *
     * The labels are Italian on purpose and do not follow the language of the
     * screen: they are data for the invoicing software, not interface.
     */
    public static function clipboardText(Tenant $tenant, ?TenantBillingProfile $profile): string
    {
        $address = $profile === null ? null : self::address($profile);

        $lines = [
            $profile?->name,
            self::line('Partita IVA', $profile?->vat_number),
            self::line('Codice fiscale', $profile?->tax_code),
            self::line('Indirizzo', $address),
            self::line('Codice SDI', $profile?->sdi_code),
            self::line('PEC', $profile?->pec),
            self::line('Referente', $profile?->contact_name),
            self::line('Email fatture e avvisi', $profile?->billing_email),
            self::line('Email', $profile?->email),
            self::line('Telefono', $profile?->phone),
            'Tenant id (License Manager): '.$tenant->license_id,
        ];

        return implode("\n", array_filter($lines, fn (?string $line): bool => $line !== null && $line !== ''));
    }

    /**
     * Where automatic notices go: the billing email when there is one, else the main one.
     */
    public static function noticeEmail(TenantBillingProfile $profile): ?string
    {
        return $profile->billing_email ?? $profile->email;
    }

    /**
     * The names of the columns that differ, for the register: never the values.
     *
     * @param  array<string, string|null>  $columns  as returned by columns()
     * @return array<int, string>
     */
    public static function changedFields(?TenantBillingProfile $current, array $columns): array
    {
        $changed = [];

        foreach ($columns as $field => $value) {
            $before = $current === null ? null : $current->getAttribute($field);
            $before = $before instanceof BillingKind ? $before->value : $before;

            if ($before !== $value) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    private static function address(TenantBillingProfile $profile): ?string
    {
        $town = trim(implode(' ', array_filter([$profile->postal_code, $profile->city])).($profile->province ? ' ('.$profile->province.')' : ''));
        $parts = array_filter([$profile->street, $town, $profile->country], fn (?string $part): bool => $part !== null && $part !== '');

        return $profile->street || $profile->city || $profile->postal_code ? implode(', ', $parts) : null;
    }

    private static function line(string $label, ?string $value): ?string
    {
        return $value === null || $value === '' ? null : $label.': '.$value;
    }

    /**
     * Free text: runs of white space become one space, ends trimmed, empty becomes null.
     */
    private static function text(mixed $value): ?string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $text === '' ? null : $text;
    }

    /**
     * Codes: all white space removed, uppercase, empty becomes null.
     */
    private static function code(mixed $value): ?string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $code = mb_strtoupper((string) preg_replace('/\s+/u', '', $value));

        return $code === '' ? null : $code;
    }

    /**
     * Email: trimmed and lowercase, empty becomes null.
     */
    private static function email(mixed $value): ?string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $email = mb_strtolower(trim($value));

        return $email === '' ? null : $email;
    }

    private static function upper(?string $value): ?string
    {
        return $value === null ? null : mb_strtoupper($value);
    }

    /**
     * A rule that fails with its own message when the test says no; an empty value is left to `required`.
     *
     * @param  callable(string): bool  $passes
     */
    private static function check(string $message, callable $passes): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($message, $passes): void {
            if (is_string($value) && $value !== '' && ! $passes($value)) {
                $fail($message);
            }
        };
    }

    /**
     * Control characters are refused in text and in codes alike.
     */
    private static function noControlCharacters(): Closure
    {
        return self::check(__('The :attribute contains invalid characters.'), fn (string $value): bool => preg_match('/[\x00-\x1f\x7f]/', $value) !== 1);
    }
}
