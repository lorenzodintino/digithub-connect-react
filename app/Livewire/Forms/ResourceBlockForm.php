<?php

namespace App\Livewire\Forms;

use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Services\AgendaLock;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class ResourceBlockForm extends Form
{
    public string $starts_at = '';

    public string $ends_at = '';

    public string $reason = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['starts_at' => __('Start'), 'ends_at' => __('End'), 'reason' => __('Reason')];
    }

    public function store(Resource $resource): ResourceBlock
    {
        $this->validate();

        $timezone = $resource->tenant->timezone;
        $startsAt = CarbonImmutable::parse($this->starts_at, $timezone)->utc();
        $endsAt = CarbonImmutable::parse($this->ends_at, $timezone)->utc();
        $block = AgendaLock::forTenant($resource->tenant_id, function () use ($resource, $startsAt, $endsAt): ResourceBlock {
            if ($resource->hasUpcomingAllocations($startsAt, $endsAt)) {
                throw ValidationException::withMessages(['blockForm.starts_at' => __('This period overlaps a resource booking. Cancel or move it first.')]);
            }

            return $resource->blocks()->create([
                'tenant_id' => $resource->tenant_id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => trim($this->reason) ?: null,
            ]);
        });

        Activity::log('resource.blocked', $resource, ['block_id' => $block->id]);
        $this->reset();

        return $block;
    }

    public function remove(ResourceBlock $block): void
    {
        Activity::log('resource.unblocked', $block->resource, ['block_id' => $block->id]);
        $block->delete();
    }
}
