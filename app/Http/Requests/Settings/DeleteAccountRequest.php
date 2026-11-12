<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class DeleteAccountRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Anyone signed in may close their own account, password in hand.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, Password|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
