<?php

use App\Enums\ResourceType;
use App\Livewire\Forms\ResourceBlockForm;
use App\Livewire\Forms\ResourceForm;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\Tenant;
use Flux\Flux;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    public ResourceForm $form;

    public ResourceBlockForm $blockForm;

    #[Locked]
    public ?int $blockingResourceId = null;

    #[Locked]
    public ?int $deletingResourceId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Resource::class);
    }

    #[Computed]
    public function tenant(): Tenant
    {
        return auth()->user()->tenant;
    }

    /** @return Collection<int, Resource> */
    #[Computed]
    public function resources(): Collection
    {
        return $this->tenant->resources()
            ->with(['blocks' => fn (HasMany $query) => $query->where('ends_at', '>', now())->orderBy('starts_at')])
            ->orderBy('name')->get();
    }

    public function createResource(): void
    {
        $this->authorize('create', Resource::class);
        $this->form->reset();
        $this->resetValidation();
        Flux::modal('resource-form')->show();
    }

    public function editResource(Resource $resource): void
    {
        $this->authorize('update', $resource);
        $this->resetValidation();
        $this->form->setResource($resource);
        Flux::modal('resource-form')->show();
    }

    public function saveResource(): void
    {
        if ($this->form->resource === null) {
            $this->authorize('create', Resource::class);
            $this->form->store($this->tenant);
            Flux::toast(variant: 'success', text: __('Resource created.'));
        } else {
            $this->authorize('update', $this->form->resource);
            $this->form->update();
            Flux::toast(variant: 'success', text: __('Resource updated.'));
        }

        Flux::modal('resource-form')->close();
        unset($this->resources);
    }

    public function blockResource(Resource $resource): void
    {
        $this->authorize('update', $resource);
        $this->blockingResourceId = $resource->id;
        $this->blockForm->reset();
        $this->resetValidation();
        Flux::modal('resource-block')->show();
    }

    #[Computed]
    public function blockingResource(): ?Resource
    {
        return $this->blockingResourceId === null ? null
            : $this->tenant->resources()->findOrFail($this->blockingResourceId);
    }

    public function saveBlock(): void
    {
        $resource = $this->tenant->resources()->findOrFail($this->blockingResourceId);
        $this->authorize('update', $resource);
        $this->blockForm->store($resource);
        $this->blockingResourceId = null;
        unset($this->resources, $this->blockingResource);
        Flux::modal('resource-block')->close();
        Flux::toast(variant: 'success', text: __('Resource unavailability saved.'));
    }

    public function removeBlock(ResourceBlock $block): void
    {
        $this->authorize('update', $block->resource);
        $this->blockForm->remove($block);
        unset($this->resources);
        Flux::toast(variant: 'success', text: __('Resource unavailability removed.'));
    }

    public function confirmDelete(Resource $resource): void
    {
        $this->authorize('delete', $resource);
        $this->deletingResourceId = $resource->id;
        $this->resetValidation();
        Flux::modal('resource-delete')->show();
    }

    public function deleteResource(): void
    {
        $resource = $this->tenant->resources()->findOrFail($this->deletingResourceId);
        $this->authorize('delete', $resource);

        if (! $this->form->delete($resource)) {
            $this->addError('deletingResourceId', __('Remove this resource from its services and cancel or move its upcoming bookings before deleting it.'));
            return;
        }

        $this->deletingResourceId = null;
        unset($this->resources);
        Flux::modal('resource-delete')->close();
        Flux::toast(variant: 'success', text: __('Resource deleted.'));
    }
}; ?>

<x-slot:title>{{ __('Resources') }}</x-slot>

<div class="w-full">
    <div class="flex flex-col gap-6">
        <x-page-header :heading="__('Resources')" :subheading="__('Rooms, cabins and equipment shared by your services.')">
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" wire:click="createResource">{{ __('New resource') }}</flux:button>
            </x-slot:actions>
        </x-page-header>

        <flux:callout>
            <flux:callout.heading>{{ __('Resource configuration') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Booking times require a free operator and all the resources needed by the service. Preparation, cleanup and unavailability are included.') }}</flux:callout.text>
        </flux:callout>

        @if ($this->resources->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                <flux:heading>{{ __('No resources yet') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Create a separate resource for each physical room, cabin or piece of equipment.') }}</flux:text>
                <flux:button class="mt-4" wire:click="createResource">{{ __('New resource') }}</flux:button>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($this->resources as $resource)
                    <div wire:key="resource-{{ $resource->id }}" class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <flux:heading class="break-words">{{ $resource->name }}</flux:heading>
                                <flux:text>{{ $resource->type->label() }}</flux:text>
                            </div>
                            <flux:badge :color="$resource->is_active ? 'emerald' : 'zinc'">{{ $resource->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                        </div>

                        @if ($resource->description)
                            <flux:text class="break-words">{{ $resource->description }}</flux:text>
                        @endif

                        <div class="flex flex-wrap gap-2">
                            <flux:badge color="zinc">{{ trans_choice('one minute of preparation|:count minutes of preparation', $resource->preparation_minutes) }}</flux:badge>
                            <flux:badge color="zinc">{{ trans_choice('one minute of cleanup|:count minutes of cleanup', $resource->cleanup_minutes) }}</flux:badge>
                        </div>

                        @if ($resource->notes)
                            <div class="space-y-1">
                                <flux:heading size="sm">{{ __('Internal notes') }}</flux:heading>
                                <flux:text class="break-words">{{ $resource->notes }}</flux:text>
                            </div>
                        @endif

                        @if ($resource->blocks->isNotEmpty())
                            <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                                <flux:heading size="sm">{{ __('Unavailability') }}</flux:heading>
                                <flux:text>{{ __('Times are shown in :timezone.', ['timezone' => $this->tenant->timezone]) }}</flux:text>
                                @foreach ($resource->blocks as $block)
                                    <div wire:key="resource-block-{{ $block->id }}" class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <flux:text>{{ $block->starts_at->setTimezone($this->tenant->timezone)->format('d/m/Y H:i') }} — {{ $block->ends_at->setTimezone($this->tenant->timezone)->format('d/m/Y H:i') }}</flux:text>
                                            @if ($block->reason)
                                                <flux:text class="break-words">{{ $block->reason }}</flux:text>
                                            @endif
                                        </div>
                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeBlock({{ $block->id }})" :aria-label="__('Remove unavailability')" />
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex flex-wrap gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <flux:button size="sm" wire:click="editResource({{ $resource->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" icon="clock" wire:click="blockResource({{ $resource->id }})">{{ __('Add unavailability') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="confirmDelete({{ $resource->id }})">{{ __('Delete') }}</flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <flux:modal name="resource-form" class="w-full max-w-xl">
        <form wire:submit="saveResource" class="space-y-5">
            <flux:heading size="lg">{{ $this->form->resource ? __('Edit resource') : __('New resource') }}</flux:heading>
            <flux:input wire:model="form.name" :label="__('Resource name')" required autofocus />
            <flux:select wire:model="form.type" :label="__('Resource type')">
                @foreach (ResourceType::cases() as $type)
                    <flux:select.option :value="$type->value" wire:key="resource-type-{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="form.description" :label="__('Description')" rows="2" />
            <flux:textarea wire:model="form.notes" :label="__('Internal notes')" :description="__('Visible only in the management panel.')" rows="2" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.preparation_minutes" type="number" min="0" max="240" :label="__('Preparation (minutes)')" required />
                <flux:input wire:model="form.cleanup_minutes" type="number" min="0" max="240" :label="__('Cleanup (minutes)')" required />
            </div>
            <flux:switch wire:model="form.is_active" :label="__('Active')" :description="__('Deactivate a resource to keep its details and service assignments.')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button type="button">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveResource">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="resource-block" class="w-full max-w-xl">
        <form wire:submit="saveBlock" class="space-y-5">
            <flux:heading size="lg">{{ __('Add unavailability') }}</flux:heading>
            <flux:text>{{ $this->blockingResource?->name }}</flux:text>
            <flux:text>{{ __('Enter times in the portal timezone: :timezone.', ['timezone' => $this->tenant->timezone]) }}</flux:text>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="blockForm.starts_at" type="datetime-local" :label="__('Start')" required />
                <flux:input wire:model="blockForm.ends_at" type="datetime-local" :label="__('End')" required />
            </div>
            <flux:input wire:model="blockForm.reason" :label="__('Reason')" :description="__('Optional. For example, maintenance or repairs.')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button type="button">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveBlock">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="resource-delete" class="w-full max-w-md">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('Delete resource?') }}</flux:heading>
            <flux:text>{{ __('Its unavailability periods will also be removed. Deactivate it if you want to keep them.') }}</flux:text>
            <flux:error name="deletingResourceId" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button>{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="deleteResource" wire:loading.attr="disabled">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
