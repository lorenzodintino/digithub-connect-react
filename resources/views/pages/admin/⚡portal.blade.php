<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Impersonation;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Tenant $tenant;

    public ?string $generatedPassword = null;

    /**
     * Authorize access to the requested portal.
     */
    public function mount(Tenant $tenant): void
    {
        $this->authorize('view', $tenant);

        $this->tenant = $tenant;
    }

    /**
     * The manager account owning this portal.
     */
    #[Computed]
    public function manager(): ?User
    {
        return $this->tenant->manager()->first();
    }

    /**
     * The operators working inside this portal.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function operators(): Collection
    {
        return $this->tenant->operators()->withCount('availabilityRules')->orderBy('name')->get();
    }

    /**
     * The services published by this portal.
     *
     * @return Collection<int, \App\Models\Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return $this->tenant->services()
            ->with(['tenant', 'packageItems.service.operators'])
            ->withCount('operators')->orderBy('position')->get()
            ->map(fn (\App\Models\Service $service) => $service->bookingConfiguration());
    }

    /**
     * Step into the portal as its manager, to see exactly what they see.
     */
    public function impersonateManager(Impersonation $impersonation): void
    {
        $this->authorize('view', $this->tenant);

        $manager = $this->manager;

        abort_if($manager === null, 404);

        $impersonation->start(auth()->user(), $manager);

        $this->redirectRoute('dashboard');
    }

    /**
     * Issue a new temporary password for the portal manager.
     */
    public function resetManagerPassword(): void
    {
        $this->authorize('update', $this->tenant);

        $manager = $this->manager;

        abort_if($manager === null, 404);

        $password = Str::password(14, symbols: false);

        $manager->update(['password' => $password]);

        $this->generatedPassword = $password;

        Flux::modal('manager-password')->show();
    }

    /**
     * Toggle the active state of this portal.
     */
    public function toggleActive(): void
    {
        $this->authorize('update', $this->tenant);

        $this->tenant->update(['is_active' => ! $this->tenant->is_active]);

        Flux::toast(variant: 'success', text: $this->tenant->is_active ? __('Portal activated.') : __('Portal suspended.'));
    }
}; ?>

<x-slot:title>{{ $tenant->name }}</x-slot>

<div class="w-full">
    <div class="flex w-full flex-col gap-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('admin.portals')" wire:navigate>{{ __('Portals') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $tenant->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <x-page-header :heading="$tenant->name" :subheading="$tenant->description">
            <x-slot:actions>
                <flux:badge :color="$tenant->is_active ? 'emerald' : 'zinc'">
                    {{ $tenant->is_active ? __('Active') : __('Suspended') }}
                </flux:badge>

                <flux:button size="sm" variant="filled" :icon="$tenant->is_active ? 'pause-circle' : 'play-circle'" wire:click="toggleActive">
                    {{ $tenant->is_active ? __('Suspend') : __('Activate') }}
                </flux:button>

                <flux:button size="sm" variant="primary" icon-trailing="arrow-top-right-on-square" :href="$tenant->publicUrl()" target="_blank">
                    {{ __('Public page') }}
                </flux:button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card icon="squares-2x2" :label="__('Services')" :value="$this->services->count()" />
            <x-stat-card icon="user-group" :label="__('Operators')" :value="$this->operators->count()" />
            <x-stat-card icon="globe-alt" :label="__('Slug')" :value="'/p/'.$tenant->slug" />
            <x-stat-card icon="clock" :label="__('Timezone')" :value="$tenant->timezone" />
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700 lg:col-span-1">
                <flux:heading size="lg">{{ __('Manager') }}</flux:heading>

                @if ($this->manager)
                    <div class="mt-4 flex items-center gap-3">
                        <x-user-avatar :user="$this->manager" />
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ $this->manager->name }}</div>
                            <flux:text size="sm" class="truncate">{{ $this->manager->email }}</flux:text>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <flux:button class="w-full" size="sm" variant="primary" icon="arrow-right-end-on-rectangle" wire:click="impersonateManager">
                            {{ __('Enter as this manager') }}
                        </flux:button>

                        <flux:text size="sm">
                            {{ __('You act as them for :minutes minutes, with a banner on every screen. Everything you do is written in the register under your name.', ['minutes' => \App\Services\Impersonation::MINUTES]) }}
                        </flux:text>

                        <flux:button class="w-full" size="sm" variant="filled" icon="key" wire:click="resetManagerPassword">
                            {{ __('Issue new password') }}
                        </flux:button>
                    </div>
                @else
                    <flux:text class="mt-2">{{ __('This portal has no manager account.') }}</flux:text>
                @endif
            </div>

            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700 lg:col-span-2">
                <flux:heading size="lg">{{ __('Contact details') }}</flux:heading>

                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt><flux:text size="sm">{{ __('Email') }}</flux:text></dt>
                        <dd class="font-medium">{{ $tenant->contact_email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt><flux:text size="sm">{{ __('Phone') }}</flux:text></dt>
                        <dd class="font-medium">{{ $tenant->contact_phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt><flux:text size="sm">{{ __('Website') }}</flux:text></dt>
                        <dd class="truncate font-medium">{{ $tenant->website ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt><flux:text size="sm">{{ __('Address') }}</flux:text></dt>
                        <dd class="font-medium">{{ $tenant->address ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('Plan') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $tenant->plan?->name ?? __('This portal is not on any plan.') }}
                        @if ($tenant->plan?->formattedPrice())
                            · {{ $tenant->plan->formattedPrice() }}
                        @endif
                    </flux:text>
                </div>

                <flux:button size="sm" variant="subtle" icon="rectangle-stack" :href="route('admin.plans')" wire:navigate>
                    {{ __('Plans') }}
                </flux:button>
            </div>

            @if ($tenant->plan)
                <dl class="mt-4 grid gap-4 sm:grid-cols-4">
                    <div>
                        <dt><flux:text size="sm">{{ __('Services') }}</flux:text></dt>
                        <dd class="font-medium tabular-nums">{{ $tenant->plan->limitLabel($tenant->plan->max_services) }}</dd>
                    </div>
                    <div>
                        <dt><flux:text size="sm">{{ __('Operators') }}</flux:text></dt>
                        <dd class="font-medium tabular-nums">{{ $tenant->plan->limitLabel($tenant->plan->max_operators) }}</dd>
                    </div>
                    <div>
                        <dt><flux:text size="sm">{{ __('Bookings / month') }}</flux:text></dt>
                        <dd class="font-medium tabular-nums">{{ $tenant->plan->limitLabel($tenant->plan->max_bookings_per_month) }}</dd>
                    </div>
                    <div>
                        <dt><flux:text size="sm">{{ __('Own SMTP') }}</flux:text></dt>
                        <dd class="font-medium">{{ $tenant->plan->allows_custom_smtp ? __('Included') : __('No') }}</dd>
                    </div>
                </dl>

                <flux:text size="sm" class="mt-3">
                    {{ __('Recorded only: nothing on this portal is blocked by these numbers yet.') }}
                </flux:text>
            @endif
        </div>

        <livewire:smtp-settings :tenant="$tenant" />

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Services') }}</flux:heading>
            </div>

            @if ($this->services->isEmpty())
                <div class="px-5 py-10 text-center">
                    <flux:text>{{ __('The manager has not created any service yet.') }}</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Service') }}</flux:table.column>
                        <flux:table.column>{{ __('Duration') }}</flux:table.column>
                        <flux:table.column>{{ __('Operators') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->services as $service)
                            <flux:table.row wire:key="service-{{ $service->id }}">
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <span class="size-2.5 rounded-full" style="background-color: {{ $service->displayColor() }}"></span>
                                        <span class="font-medium">{{ $service->name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $service->formattedDuration() }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $service->operators_count }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$service->is_active ? 'emerald' : 'zinc'" inset="top bottom">
                                        {{ $service->is_active ? __('Published') : __('Hidden') }}
                                    </flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Operators') }}</flux:heading>
            </div>

            @if ($this->operators->isEmpty())
                <div class="px-5 py-10 text-center">
                    <flux:text>{{ __('No operator has been added to this portal.') }}</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                        <flux:table.column>{{ __('Weekly slots') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->operators as $operator)
                            <flux:table.row wire:key="operator-{{ $operator->id }}">
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        <x-user-avatar :user="$operator" size="xs" />
                                        <span class="font-medium">{{ $operator->name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $operator->email }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $operator->availability_rules_count }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$operator->is_active ? 'emerald' : 'zinc'" inset="top bottom">
                                        {{ $operator->is_active ? __('Active') : __('Disabled') }}
                                    </flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </div>

    <flux:modal name="manager-password" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New password issued') }}</flux:heading>
                <flux:subheading>{{ __('Share it with the manager. It is shown only once.') }}</flux:subheading>
            </div>

            <flux:input readonly :label="__('Temporary password')" :value="$generatedPassword" copyable />

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary" type="button">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
