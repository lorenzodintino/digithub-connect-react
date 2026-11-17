<?php

namespace App\Http\Requests;

use App\Enums\PlanRequestStatus;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user?->isAdmin() ?? false;
    }

    /**
     * An administrator moves a request along; withdrawing is the manager's own act.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([PlanRequestStatus::InProgress->value, PlanRequestStatus::Handled->value, PlanRequestStatus::Rejected->value])],
            'response' => ['nullable', 'string', 'max:500', Rule::requiredIf($this->input('status') === PlanRequestStatus::Rejected->value)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['response.required' => __('Say why: the manager reads it.')];
    }
}
