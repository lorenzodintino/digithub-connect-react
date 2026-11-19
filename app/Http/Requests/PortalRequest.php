<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use App\Support\BillingData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PortalRequest extends FormRequest
{
    /**
     * A new portal with its manager, or the details of an existing one.
     */
    public function authorize(): bool
    {
        $portal = $this->portal();
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // The form carries the billing section, which only the administrator may touch.
        return $portal instanceof Tenant
            ? $user->can('update', $portal) && $user->can('manageBilling', $portal)
            : $user->can('create', Tenant::class);
    }

    /**
     * Clean the billing section before it is judged: codes without spaces and
     * uppercase, emails lowercase, and the fields of the other kind dropped.
     */
    protected function prepareForValidation(): void
    {
        $billing = $this->input('billing');

        $this->merge(['billing' => BillingData::normalise(is_array($billing) ? $billing : [])]);
    }

    /**
     * The manager account only exists on creation: afterwards it belongs to
     * the person, and the portal form leaves it alone.
     *
     * A new demo portal skips billing. An existing portal with no billing
     * profile may be saved with the section left empty. Once anything is
     * filled in on an existing portal, the full billing rules apply.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $portal = $this->portal();

        /** @var array<string, string|null> $billing */
        $billing = $this->input('billing');
        $billingOptional = $portal !== null && $portal->billingProfile === null && BillingData::isEmpty($billing);
        $billingRules = $billingOptional ? ['billing' => ['nullable', 'array']] : BillingData::rules($billing);

        if ($portal === null && $this->boolean('demo')) {
            $billingRules = ['billing' => ['exclude']];
        }

        return [
            ...$billingRules,
            'demo' => [Rule::excludeIf($portal !== null), 'sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($portal?->getKey())],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'manager_name' => [Rule::requiredIf($portal === null), 'nullable', 'string', 'max:120'],
            'manager_email' => [Rule::requiredIf($portal === null), 'nullable', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'manager_name' => __('manager name'),
            'manager_email' => __('manager email'),
            'brand_color' => __('brand color'),
            'demo' => __('Demo portal'),
            ...BillingData::attributes(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return BillingData::messages();
    }

    public function portal(): ?Tenant
    {
        $portal = $this->route('tenant');

        return $portal instanceof Tenant ? $portal : null;
    }
}
