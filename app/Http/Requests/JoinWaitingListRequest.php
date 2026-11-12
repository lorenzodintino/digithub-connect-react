<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Somebody asking to be written to when a place opens up in a period.
 */
class JoinWaitingListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * One entry per service and address, whatever the casing it is typed in.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Customer::normaliseEmail($this->input('email'))]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'from' => ['required', 'date', 'after_or_equal:today'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'privacy' => ['accepted'],
            'tz' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('name'),
            'email' => __('email'),
            'phone' => __('phone'),
            'from' => __('first day'),
            'to' => __('last day'),
            'privacy' => __('privacy consent'),
        ];
    }
}
