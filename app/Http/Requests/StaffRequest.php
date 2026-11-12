<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StaffRequest extends FormRequest
{
    /**
     * A new operator, or one of the manager's own portal.
     */
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof User
            ? $this->user()?->can('update', $member) ?? false
            : $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string|Unique>>
     */
    public function rules(): array
    {
        $member = $this->route('member');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member instanceof User ? $member->getKey() : null)],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'is_bookable' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'service_ids' => ['array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ];
    }
}
