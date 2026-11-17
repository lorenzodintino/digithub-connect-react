<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DemoRequestRequest extends FormRequest
{
    /**
     * The longest message a visitor can leave.
     */
    public const MESSAGE_LIMIT = 2000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // One line only: these end up in the subject and in the headers of the email.
            'name' => ['required', 'string', 'max:120', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'email', 'max:255'],
            'business' => ['nullable', 'string', 'max:160', 'not_regex:/[\r\n]/'],
            'plan' => ['nullable', 'string', 'max:60', 'not_regex:/[\r\n]/'],
            'message' => ['nullable', 'string', 'max:'.self::MESSAGE_LIMIT],
            'privacy' => ['accepted'],
            // The trap for robots: a field no person sees, so no person fills it.
            'website' => ['nullable', 'string', 'max:255'],
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
            'business' => __('business'),
            'plan' => __('plan'),
            'message' => __('message'),
            'privacy' => __('privacy consent'),
        ];
    }
}
