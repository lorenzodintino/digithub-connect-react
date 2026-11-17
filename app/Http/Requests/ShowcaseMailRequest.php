<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowcaseMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'recipient' => ['nullable', 'email', 'max:255'],
            'mail_host' => ['nullable', 'string', 'max:255', MailSettingsRequest::hostRule()],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return MailSettingsRequest::attributeNames() + ['recipient' => __('recipient')];
    }
}
