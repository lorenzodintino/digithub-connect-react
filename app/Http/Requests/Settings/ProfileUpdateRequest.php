<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Anyone signed in edits their own profile; the route requires a session.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Name, email and preferences for everybody; the public profile only for
     * people who work inside a portal and can appear on its booking page.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        $rules = $this->profileRules($user->id) + $this->preferenceRules();

        if ($user->belongsToTenant()) {
            $rules += $this->publicProfileRules();
        }

        return $rules;
    }
}
