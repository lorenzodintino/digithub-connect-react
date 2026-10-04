<?php

namespace App\Livewire\Forms;

use App\Enums\ResourceType;
use App\Models\Resource;
use App\Models\Tenant;
use App\Services\AgendaLock;
use App\Support\Activity;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ResourceForm extends Form
{
    public ?Resource $resource = null;

    public string $name = '';

    public string $type = 'room';

    public string $description = '';

    public string $notes = '';

    public int $preparation_minutes = 0;

    public int $cleanup_minutes = 0;

    public bool $is_active = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique(Resource::class, 'name')->where('tenant_id', auth()->user()->tenant_id)->ignore($this->resource)],
            'type' => ['required', Rule::enum(ResourceType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'preparation_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'cleanup_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('Resource name'),
            'type' => __('Resource type'),
            'preparation_minutes' => __('Preparation (minutes)'),
            'cleanup_minutes' => __('Cleanup (minutes)'),
        ];
    }

    public function setResource(Resource $resource): void
    {
        $this->resource = $resource;
        $this->name = $resource->name;
        $this->type = $resource->type->value;
        $this->description = $resource->description ?? '';
        $this->notes = $resource->notes ?? '';
        $this->preparation_minutes = $resource->preparation_minutes;
        $this->cleanup_minutes = $resource->cleanup_minutes;
        $this->is_active = $resource->is_active;
    }

    public function store(Tenant $tenant): Resource
    {
        $this->name = trim($this->name);
        $attributes = $this->validate();

        $resource = $tenant->resources()->create($this->attributes($attributes));
        Activity::log('resource.created', $resource);
        $this->reset();

        return $resource;
    }

    public function update(): void
    {
        $this->name = trim($this->name);
        $attributes = $this->validate();

        AgendaLock::forTenant($this->resource->tenant_id, function () use ($attributes): void {
            $this->resource->refresh();
            if (! $this->is_active && $this->resource->hasUpcomingAllocations()) {
                throw ValidationException::withMessages(['form.is_active' => __('This resource has upcoming bookings. Cancel or move them before deactivating it.')]);
            }
            $this->resource->update($this->attributes($attributes));
            Activity::log('resource.updated', $this->resource);
        });
    }

    public function delete(Resource $resource): bool
    {
        return AgendaLock::forTenant($resource->tenant_id, function () use ($resource): bool {
            if ($resource->requirements()->exists() || $resource->hasUpcomingAllocations()) {
                return false;
            }

            Activity::log('resource.deleted', $resource);
            $resource->delete();

            return true;
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function attributes(array $attributes): array
    {
        $attributes['description'] = trim($this->description) ?: null;
        $attributes['notes'] = trim($this->notes) ?: null;

        return $attributes;
    }
}
