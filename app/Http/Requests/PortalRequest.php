<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;

class PortalRequest extends FormRequest
{
    /**
     * A new portal with its manager, or the details of an existing one.
     */
    public function authorize(): bool
    {
        $portal = $this->portal();

        return $portal instanceof Tenant
            ? $this->user()?->can('update', $portal) ?? false
            : $this->user()?->can('create', Tenant::class) ?? false;
    }

    /**
     * The manager account only exists on creation: afterwards it belongs to
     * the person, and the portal form leaves it alone.
     *
     * @return array<string, array<int, ValidationRule|RequiredIf|Unique|string>>
     */
    public function rules(): array
    {
        $portal = $this->portal();

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($portal?->getKey())],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'plan_id' => ['nullable', 'exists:plans,id'],
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
        ];
    }

    public function portal(): ?Tenant
    {
        $portal = $this->route('tenant');

        return $portal instanceof Tenant ? $portal : null;
    }
}
