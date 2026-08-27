<?php

use App\Enums\LocationType;
use App\Livewire\Forms\ServiceForm;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Services')] class extends Component {
    public ServiceForm $form;

    public ?int $deletingId = null;

    /**
     * The portal owning the services shown on this page.
     */
    #[Computed]
    public function tenant(): Tenant
    {
        return auth()->user()->tenant;
    }

    /**
     * The services of the current portal.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return $this->tenant->services()->with('operators')->orderBy('position')->orderBy('name')->get();
    }

    /**
     * The staff members that can be assigned to a service.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function assignableStaff(): Collection
    {
        return $this->tenant->users()->bookable()->orderBy('name')->get();
    }

    /**
     * Open the modal with an empty service form.
     */
    public function createService(): void
    {
        $this->authorize('create', Service::class);

        $this->form->reset();
        $this->resetValidation();
        $this->form->color = $this->tenant->brand_color;

        Flux::modal('service-form')->show();
    }

    /**
     * Open the modal preloaded with the given service.
     */
    public function editService(Service $service): void
    {
        $this->authorize('update', $service);

        $this->resetValidation();
        $this->form->setService($service);

        Flux::modal('service-form')->show();
    }

    /**
     * Persist the service currently loaded in the form.
     */
    public function saveService(): void
    {
        if ($this->form->service === null) {
            $this->authorize('create', Service::class);

            $this->form->store($this->tenant);

            Flux::toast(variant: 'success', text: __('Service created.'));
        } else {
            $this->authorize('update', $this->form->service);

            $this->form->update();

            Flux::toast(variant: 'success', text: __('Service updated.'));
        }

        Flux::modal('service-form')->close();

        unset($this->services);
    }

    /**
     * Publish or hide the given service.
     */
    public function toggleActive(Service $service): void
    {
        $this->authorize('update', $service);

        $service->update(['is_active' => ! $service->is_active]);

        unset($this->services);
    }

    /**
     * Ask for confirmation before removing a service.
     */
    public function confirmDelete(int $serviceId): void
    {
        $this->deletingId = $serviceId;

        Flux::modal('service-delete')->show();
    }

    /**
     * Permanently remove the selected service.
     */
    public function deleteService(): void
    {
        $service = Service::findOrFail($this->deletingId);

        $this->authorize('delete', $service);

        $service->delete();

        $this->deletingId = null;

        unset($this->services);

        Flux::modal('service-delete')->close();
        Flux::toast(variant: 'success', text: __('Service deleted.'));
    }
}; ?>

<x-slot:title>{{ __('Services') }}</x-slot>

<div class="w-full">
    <div class="flex w-full flex-col gap-6">
        <x-page-header :heading="__('Services')" :subheading="__('What your customers can book, how long it takes and who delivers it.')">
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" wire:click="createService">
                    {{ __('New service') }}
                </flux:button>
            </x-slot:actions>
        </x-page-header>

        @if ($this->services->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-20 text-center dark:border-zinc-700">
                <flux:icon name="squares-2x2" class="size-10 text-zinc-400" />
                <flux:heading size="lg">{{ __('No services yet') }}</flux:heading>
                <flux:text class="max-w-md">{{ __('Create your first service to start receiving bookings on your public page.') }}</flux:text>
                <flux:button class="mt-2" variant="primary" icon="plus" wire:click="createService">
                    {{ __('New service') }}
                </flux:button>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->services as $service)
                    <div
                        class="group relative flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900"
                        wire:key="service-{{ $service->id }}"
                    >
                        <div class="h-1.5 w-full" style="background-color: {{ $service->color }}"></div>

                        <div class="flex flex-1 flex-col gap-4 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <flux:heading size="lg" class="truncate">{{ $service->name }}</flux:heading>
                                    <flux:text size="sm" class="truncate">/{{ $service->slug }}</flux:text>
                                </div>

                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" inset="top bottom" />

                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="editService({{ $service->id }})">
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="arrow-top-right-on-square" :href="route('portal.service', [$this->tenant, $service])" target="_blank">
                                            {{ __('Preview') }}
                                        </flux:menu.item>
                                        <flux:menu.item :icon="$service->is_active ? 'eye-slash' : 'eye'" wire:click="toggleActive({{ $service->id }})">
                                            {{ $service->is_active ? __('Hide') : __('Publish') }}
                                        </flux:menu.item>

                                        <flux:menu.separator />

                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $service->id }})">
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>

                            @if ($service->description)
                                <flux:text size="sm" class="line-clamp-2">{{ $service->description }}</flux:text>
                            @endif

                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" icon="clock" color="zinc">{{ $service->formattedDuration() }}</flux:badge>

                                <flux:badge size="sm" :icon="$service->location_type->icon()" color="zinc">
                                    {{ $service->location_type->label() }}
                                </flux:badge>

                                @if ($service->formattedPrice())
                                    <flux:badge size="sm" color="zinc">{{ $service->formattedPrice() }}</flux:badge>
                                @endif

                                @if ($service->requires_confirmation)
                                    <flux:badge size="sm" color="amber">{{ __('Needs approval') }}</flux:badge>
                                @endif
                            </div>

                            <div class="mt-auto flex items-center justify-between gap-3 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                                @if ($service->operators->isEmpty())
                                    <flux:badge size="sm" color="rose">{{ __('No operator assigned') }}</flux:badge>
                                @else
                                    <flux:avatar.group>
                                        @foreach ($service->operators->take(4) as $operator)
                                            <flux:avatar size="xs" :name="$operator->name" :initials="$operator->initials()" />
                                        @endforeach
                                    </flux:avatar.group>
                                @endif

                                <flux:badge size="sm" :color="$service->is_active ? 'emerald' : 'zinc'">
                                    {{ $service->is_active ? __('Published') : __('Hidden') }}
                                </flux:badge>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <flux:modal name="service-form" class="w-full max-w-2xl" variant="flyout">
        <form wire:submit="saveService" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $this->form->service ? __('Edit service') : __('New service') }}
                </flux:heading>
                <flux:subheading>{{ __('Duration, buffers and booking rules define how slots are generated.') }}</flux:subheading>
            </div>

            <flux:input wire:model="form.name" :label="__('Service name')" required autofocus />
            <flux:textarea wire:model="form.description" :label="__('Description')" rows="3" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.duration_minutes" type="number" min="5" step="5" :label="__('Duration (minutes)')" required />
                <flux:input wire:model="form.slot_interval_minutes" type="number" min="5" step="5" :label="__('Slot interval (minutes)')" required />
                <flux:input wire:model="form.buffer_before_minutes" type="number" min="0" step="5" :label="__('Buffer before (minutes)')" />
                <flux:input wire:model="form.buffer_after_minutes" type="number" min="0" step="5" :label="__('Buffer after (minutes)')" />
                <flux:input wire:model="form.min_notice_minutes" type="number" min="0" step="15" :label="__('Minimum notice (minutes)')" />
                <flux:input wire:model="form.max_advance_days" type="number" min="1" :label="__('Bookable up to (days)')" />
                <flux:input wire:model="form.price" type="number" min="0" step="0.01" :label="__('Price')" :description="__('Leave empty for a free service.')" />
                <flux:input wire:model="form.color" type="color" :label="__('Color')" />
            </div>

            <flux:separator :text="__('Where it takes place')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="form.location_type" :label="__('Location type')">
                    @foreach (LocationType::cases() as $case)
                        <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="form.location_value"
                    :label="__('Location details')"
                    :placeholder="LocationType::from($this->form->location_type)->valuePlaceholder()"
                />
            </div>

            <flux:separator :text="__('Who delivers it')" />

            @if ($this->assignableStaff->isEmpty())
                <flux:callout icon="exclamation-triangle" variant="warning">
                    {{ __('No bookable people yet. Add operators from the Staff page first.') }}
                </flux:callout>
            @else
                <flux:checkbox.group wire:model="form.operator_ids" :label="__('Operators')">
                    @foreach ($this->assignableStaff as $member)
                        <flux:checkbox :value="$member->id" :label="$member->name" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <div class="space-y-3">
                <flux:switch wire:model="form.requires_confirmation" :label="__('Bookings need manual approval')" />
                <flux:switch wire:model="form.is_active" :label="__('Published on the public page')" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">
                    {{ $this->form->service ? __('Save changes') : __('Create service') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="service-delete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this service?') }}</flux:heading>
                <flux:subheading>{{ __('It will disappear from your public page immediately.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteService">{{ __('Delete service') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
