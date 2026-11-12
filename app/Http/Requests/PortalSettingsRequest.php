<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PortalSettingsRequest extends FormRequest
{
    /**
     * The manager edits their own portal.
     */
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user?->tenant !== null && $user->can('update', $user->tenant);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'privacy_policy_url' => ['nullable', 'url', 'max:255'],
            'terms_url' => ['nullable', 'url', 'max:255'],
            'data_controller' => ['nullable', 'string', 'max:255'],
            'data_retention_days' => ['nullable', 'integer', 'min:30', 'max:3650'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'privacy_policy_url' => __('privacy policy link'),
            'terms_url' => __('terms link'),
            'data_controller' => __('data controller'),
            'data_retention_days' => __('retention'),
        ];
    }
}
