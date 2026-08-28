<?php

namespace App\Livewire\Forms;

use App\Models\Plan;
use Livewire\Form;

class PlanForm extends Form
{
    public ?Plan $plan = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public string $max_services = '';

    public string $max_operators = '';

    public string $max_bookings_per_month = '';

    public bool $allows_custom_smtp = false;

    public bool $is_active = true;

    public string $position = '0';

    /**
     * Validation rules for a commercial plan.
     *
     * Every limit is optional: an empty field means the plan does not cap that
     * number at all.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'max_services' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_operators' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'max_bookings_per_month' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'allows_custom_smtp' => ['boolean'],
            'is_active' => ['boolean'],
            'position' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * Attribute names used in validation messages.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'max_services' => __('services limit'),
            'max_operators' => __('operators limit'),
            'max_bookings_per_month' => __('bookings limit'),
        ];
    }

    /**
     * Fill the form from an existing plan.
     */
    public function setPlan(Plan $plan): void
    {
        $this->plan = $plan;

        $this->name = $plan->name;
        $this->description = $plan->description ?? '';
        $this->price = $plan->price_cents === null ? '' : (string) ($plan->price_cents / 100);
        $this->max_services = (string) ($plan->max_services ?? '');
        $this->max_operators = (string) ($plan->max_operators ?? '');
        $this->max_bookings_per_month = (string) ($plan->max_bookings_per_month ?? '');
        $this->allows_custom_smtp = $plan->allows_custom_smtp;
        $this->is_active = $plan->is_active;
        $this->position = (string) $plan->position;
    }

    /**
     * Create the plan.
     */
    public function store(): Plan
    {
        $this->validate();

        $plan = Plan::create($this->planAttributes());

        $this->reset();

        return $plan;
    }

    /**
     * Persist the changes to the loaded plan.
     */
    public function update(): Plan
    {
        $this->validate();

        $this->plan->update($this->planAttributes());

        return $this->plan;
    }

    /**
     * The plan attributes extracted from the form state.
     *
     * @return array<string, mixed>
     */
    protected function planAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'price_cents' => $this->price === '' ? null : (int) round((float) $this->price * 100),
            'currency' => 'EUR',
            'max_services' => $this->max_services === '' ? null : (int) $this->max_services,
            'max_operators' => $this->max_operators === '' ? null : (int) $this->max_operators,
            'max_bookings_per_month' => $this->max_bookings_per_month === '' ? null : (int) $this->max_bookings_per_month,
            'allows_custom_smtp' => $this->allows_custom_smtp,
            'is_active' => $this->is_active,
            'position' => (int) $this->position,
        ];
    }
}
