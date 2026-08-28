<?php

use App\Livewire\Forms\PlanForm;
use App\Models\Plan;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Plans')] class extends Component {
    public PlanForm $form;

    #[Locked]
    public ?int $deletingId = null;

    /**
     * The plans on offer, in the order they are meant to be read.
     *
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::query()
            ->withCount('tenants')
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    /**
     * Open the modal with an empty plan.
     */
    public function createPlan(): void
    {
        $this->authorize('create', Plan::class);

        $this->form->reset();
        $this->resetValidation();

        Flux::modal('plan-form')->show();
    }

    /**
     * Open the modal preloaded with the given plan.
     */
    public function editPlan(Plan $plan): void
    {
        $this->authorize('update', $plan);

        $this->resetValidation();
        $this->form->setPlan($plan);

        Flux::modal('plan-form')->show();
    }

    /**
     * Create or update the plan in the form.
     */
    public function savePlan(): void
    {
        $isNew = $this->form->plan === null;

        if ($isNew) {
            $this->authorize('create', Plan::class);
            $this->form->store();
        } else {
            $this->authorize('update', $this->form->plan);
            $this->form->update();
        }

        unset($this->plans);

        Flux::modal('plan-form')->close();
        Flux::toast(variant: 'success', text: $isNew ? __('Plan created.') : __('Plan updated.'));
    }

    /**
     * Take a plan off the price list without touching the portals on it.
     */
    public function toggleActive(Plan $plan): void
    {
        $this->authorize('update', $plan);

        $plan->update(['is_active' => ! $plan->is_active]);

        unset($this->plans);

        Flux::toast(variant: 'success', text: $plan->is_active ? __('Plan is on sale again.') : __('Plan retired.'));
    }

    /**
     * Ask before removing a plan.
     */
    public function confirmDelete(int $planId): void
    {
        $this->deletingId = $planId;

        Flux::modal('plan-delete')->show();
    }

    /**
     * Delete a plan nobody is on.
     */
    public function deletePlan(): void
    {
        $plan = Plan::query()->withCount('tenants')->findOrFail($this->deletingId);

        $this->authorize('delete', $plan);

        $plan->delete();

        $this->deletingId = null;

        unset($this->plans);

        Flux::modal('plan-delete')->close();
        Flux::toast(variant: 'success', text: __('Plan deleted.'));
    }
}; ?>

<x-slot:title>{{ __('Plans') }}</x-slot>

<div class="w-full">
    <div class="flex w-full flex-col gap-6">
        <x-page-header :heading="__('Plans')" :subheading="__('What each portal is entitled to. Defined here first; the limits are not enforced yet.')">
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" wire:click="createPlan">
                    {{ __('New plan') }}
                </flux:button>
            </x-slot:actions>
        </x-page-header>

        <flux:callout icon="information-circle">
            {{ __('Nothing is blocked by these numbers yet: a portal over its limit keeps working. Enforcement comes once the plans are settled.') }}
        </flux:callout>

        @if ($this->plans->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-20 text-center dark:border-zinc-700">
                <flux:icon name="rectangle-stack" class="size-10 text-zinc-400" />
                <flux:heading size="lg">{{ __('No plan yet') }}</flux:heading>
                <flux:text>{{ __('Create the first plan to describe what you sell.') }}</flux:text>
                <flux:button class="mt-2" variant="primary" icon="plus" wire:click="createPlan">
                    {{ __('New plan') }}
                </flux:button>
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Plan') }}</flux:table.column>
                        <flux:table.column>{{ __('Price') }}</flux:table.column>
                        <flux:table.column>{{ __('Services') }}</flux:table.column>
                        <flux:table.column>{{ __('Operators') }}</flux:table.column>
                        <flux:table.column>{{ __('Bookings / month') }}</flux:table.column>
                        <flux:table.column>{{ __('Own SMTP') }}</flux:table.column>
                        <flux:table.column>{{ __('Portals') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->plans as $plan)
                            <flux:table.row wire:key="plan-{{ $plan->id }}">
                                <flux:table.cell>
                                    <div class="font-medium">{{ $plan->name }}</div>

                                    @if ($plan->description)
                                        <flux:text size="sm" class="whitespace-normal">{{ $plan->description }}</flux:text>
                                    @endif

                                    @unless ($plan->is_active)
                                        <flux:badge size="sm" color="zinc" class="mt-1">{{ __('Retired') }}</flux:badge>
                                    @endunless
                                </flux:table.cell>

                                <flux:table.cell class="tabular-nums">
                                    {{ $plan->formattedPrice() ?? __('Free') }}
                                </flux:table.cell>

                                <flux:table.cell class="tabular-nums">{{ $plan->limitLabel($plan->max_services) }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $plan->limitLabel($plan->max_operators) }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $plan->limitLabel($plan->max_bookings_per_month) }}</flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$plan->allows_custom_smtp ? 'emerald' : 'zinc'">
                                        {{ $plan->allows_custom_smtp ? __('Included') : __('No') }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell class="tabular-nums">{{ $plan->tenants_count }}</flux:table.cell>

                                <flux:table.cell align="end">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" inset="top bottom" />

                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="editPlan({{ $plan->id }})">
                                                {{ __('Edit') }}
                                            </flux:menu.item>

                                            <flux:menu.item
                                                :icon="$plan->is_active ? 'eye-slash' : 'eye'"
                                                wire:click="toggleActive({{ $plan->id }})"
                                            >
                                                {{ $plan->is_active ? __('Retire') : __('Put on sale') }}
                                            </flux:menu.item>

                                            @can('delete', $plan)
                                                <flux:menu.separator />

                                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $plan->id }})">
                                                    {{ __('Delete') }}
                                                </flux:menu.item>
                                            @endcan
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </div>

    <flux:modal name="plan-form" class="w-full max-w-xl" variant="flyout">
        <form wire:submit="savePlan" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $this->form->plan ? __('Edit plan') : __('New plan') }}
                </flux:heading>
                <flux:subheading>{{ __('Leave a limit empty to grant it without a cap.') }}</flux:subheading>
            </div>

            <flux:input wire:model="form.name" :label="__('Plan name')" required autofocus />
            <flux:input wire:model="form.description" :label="__('Description')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    wire:model="form.price"
                    type="number"
                    min="0"
                    step="0.01"
                    :label="__('Monthly price')"
                    :description="__('Indicative: nothing is billed from here yet.')"
                />

                <flux:input wire:model="form.position" type="number" min="0" :label="__('Order')" />
                <flux:input wire:model="form.max_services" type="number" min="1" :label="__('Services limit')" />
                <flux:input wire:model="form.max_operators" type="number" min="1" :label="__('Operators limit')" />
                <flux:input wire:model="form.max_bookings_per_month" type="number" min="1" :label="__('Bookings per month')" />
            </div>

            <div class="space-y-3">
                <flux:switch wire:model="form.allows_custom_smtp" :label="__('Can send from its own SMTP server')" />
                <flux:switch wire:model="form.is_active" :label="__('On sale')" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">
                    {{ $this->form->plan ? __('Save changes') : __('Create plan') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="plan-delete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this plan?') }}</flux:heading>
                <flux:subheading>{{ __('Only a plan with no portal on it can be deleted. Retire it instead to stop selling it.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deletePlan">{{ __('Delete plan') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
