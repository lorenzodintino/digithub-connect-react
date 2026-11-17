<?php

namespace App\Http\Requests;

use App\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan
            ? $this->user()?->can('update', $plan) ?? false
            : $this->user()?->can('create', Plan::class) ?? false;
    }

    /**
     * Every limit is optional: an empty field means the plan does not cap
     * that number at all.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'max_services' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_operators' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_bookings_per_month' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_waiting_list' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_packages' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'features' => ['sometimes', 'array:'.implode(',', Plan::FEATURES)],
            'features.*' => ['boolean'],
            'is_active' => ['required', 'boolean'],
            'position' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'max_services' => __('services limit'),
            'max_operators' => __('operators limit'),
            'max_bookings_per_month' => __('bookings limit'),
            'max_waiting_list' => __('waiting list limit'),
            'max_packages' => __('packages limit'),
        ];
    }
}
