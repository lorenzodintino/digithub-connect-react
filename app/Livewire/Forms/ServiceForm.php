<?php

namespace App\Livewire\Forms;

use App\Enums\LocationType;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ServiceForm extends Form
{
    public ?Service $service = null;

    public string $name = '';

    public string $description = '';

    public int $duration_minutes = 30;

    public int $buffer_before_minutes = 0;

    public int $buffer_after_minutes = 0;

    public int $slot_interval_minutes = 15;

    public int $min_notice_minutes = 120;

    public int $max_advance_days = 60;

    public string $price = '';

    public string $color = '#4f46e5';

    public string $location_type = 'in_person';

    public string $location_value = '';

    public bool $requires_confirmation = false;

    public bool $is_active = true;

    /**
     * The operator ids assigned to this service.
     *
     * @var array<int, int>
     */
    public array $operator_ids = [];

    /**
     * Validation rules for a portal service.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'buffer_before_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'buffer_after_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'slot_interval_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'min_notice_minutes' => ['required', 'integer', 'min:0', 'max:20160'],
            'max_advance_days' => ['required', 'integer', 'min:1', 'max:365'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'location_type' => ['required', Rule::enum(LocationType::class)],
            'location_value' => ['nullable', 'string', 'max:255'],
            'requires_confirmation' => ['boolean'],
            'is_active' => ['boolean'],
            'operator_ids' => ['array'],
            'operator_ids.*' => ['integer', 'exists:users,id'],
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
            'duration_minutes' => __('duration'),
            'buffer_before_minutes' => __('buffer before'),
            'buffer_after_minutes' => __('buffer after'),
            'slot_interval_minutes' => __('slot interval'),
            'min_notice_minutes' => __('minimum notice'),
            'max_advance_days' => __('booking window'),
            'location_value' => __('location details'),
            'operator_ids' => __('operators'),
        ];
    }

    /**
     * Fill the form from an existing service.
     */
    public function setService(Service $service): void
    {
        $this->service = $service;

        $this->fill($service->only([
            'name', 'duration_minutes', 'buffer_before_minutes', 'buffer_after_minutes',
            'slot_interval_minutes', 'min_notice_minutes', 'max_advance_days', 'color',
            'requires_confirmation', 'is_active',
        ]));

        $this->description = $service->description ?? '';
        $this->location_type = $service->location_type->value;
        $this->location_value = $service->location_value ?? '';
        $this->price = $service->price_cents === null ? '' : (string) ($service->price_cents / 100);
        $this->operator_ids = $service->operators()->pluck('users.id')->all();
    }

    /**
     * Create a new service inside the given portal.
     */
    public function store(Tenant $tenant): Service
    {
        $this->validate();

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'slug' => Service::uniqueSlug($this->name, $tenant->id),
            'position' => (int) Service::query()->forTenant($tenant)->max('position') + 1,
            ...$this->serviceAttributes(),
        ]);

        $service->operators()->sync($this->operator_ids);

        $this->reset();

        return $service;
    }

    /**
     * Persist changes to the loaded service.
     */
    public function update(): Service
    {
        $this->validate();

        $this->service->update($this->serviceAttributes());
        $this->service->operators()->sync($this->operator_ids);

        return $this->service;
    }

    /**
     * The service attributes extracted from the form state.
     *
     * @return array<string, mixed>
     */
    protected function serviceAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'duration_minutes' => $this->duration_minutes,
            'buffer_before_minutes' => $this->buffer_before_minutes,
            'buffer_after_minutes' => $this->buffer_after_minutes,
            'slot_interval_minutes' => $this->slot_interval_minutes,
            'min_notice_minutes' => $this->min_notice_minutes,
            'max_advance_days' => $this->max_advance_days,
            'price_cents' => $this->price === '' ? null : (int) round((float) $this->price * 100),
            'color' => $this->color,
            'location_type' => $this->location_type,
            'location_value' => $this->location_value ?: null,
            'requires_confirmation' => $this->requires_confirmation,
            'is_active' => $this->is_active,
        ];
    }
}
