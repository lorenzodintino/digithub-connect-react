<?php

use App\Livewire\Forms\PortalForm;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Search;
use App\Support\Timezones;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Portals')] class extends Component {
    use WithPagination;

    public PortalForm $form;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    public ?int $deletingId = null;

    /**
     * The generated manager password, shown once after provisioning a portal.
     */
    public ?string $generatedPassword = null;

    public ?string $generatedEmail = null;

    /**
     * The portals matching the current filters.
     *
     * @return LengthAwarePaginator<int, Tenant>
     */
    #[Computed]
    public function portals(): LengthAwarePaginator
    {
        return Tenant::query()
            ->withCount(['services', 'users'])
            ->with('manager')
            ->when($this->search !== '', fn ($query) => Search::contains(
                $query,
                ['name', 'slug', 'contact_email'],
                $this->search,
            ))
            ->when($this->status !== 'all', fn ($query) => $query->where('is_active', $this->status === 'active'))
            ->latest()
            ->paginate(10);
    }

    /**
     * Reset pagination whenever the search term changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination whenever the status filter changes.
     */
    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Keep the slug in sync with the portal name while creating.
     */
    public function updatedFormName(string $value): void
    {
        if ($this->form->portal === null) {
            $this->form->slug = Str::slug($value);
        }
    }

    /**
     * Open the modal with an empty portal form.
     */
    public function createPortal(): void
    {
        $this->authorize('create', Tenant::class);

        $this->form->reset();
        $this->resetValidation();

        Flux::modal('portal-form')->show();
    }

    /**
     * Open the modal preloaded with the given portal.
     */
    public function editPortal(Tenant $portal): void
    {
        $this->authorize('update', $portal);

        $this->resetValidation();
        $this->form->setPortal($portal);

        Flux::modal('portal-form')->show();
    }

    /**
     * Persist the portal currently loaded in the form.
     */
    public function savePortal(): void
    {
        $isNew = $this->form->portal === null;

        $this->authorize($isNew ? 'create' : 'update', $isNew ? Tenant::class : $this->form->portal);

        if ($isNew) {
            ['portal' => $portal, 'password' => $password] = $this->form->store();

            $this->generatedEmail = $portal->manager?->email;
            $this->generatedPassword = $password;

            Flux::modal('portal-form')->close();
            Flux::modal('manager-credentials')->show();
        } else {
            $this->form->update();

            Flux::modal('portal-form')->close();
            Flux::toast(variant: 'success', text: __('Portal updated.'));
        }

        unset($this->portals);
    }

    /**
     * Toggle the active state of the given portal.
     */
    public function toggleActive(Tenant $portal): void
    {
        $this->authorize('update', $portal);

        $portal->update(['is_active' => ! $portal->is_active]);

        unset($this->portals);

        Flux::toast(variant: 'success', text: $portal->is_active ? __('Portal activated.') : __('Portal suspended.'));
    }

    /**
     * Ask for confirmation before removing a portal.
     */
    public function confirmDelete(int $portalId): void
    {
        $this->deletingId = $portalId;

        unset($this->deletingUpcomingCount);

        Flux::modal('portal-delete')->show();
    }

    /**
     * How many upcoming bookings would go down with the portal being deleted.
     */
    #[Computed]
    public function deletingUpcomingCount(): int
    {
        if ($this->deletingId === null) {
            return 0;
        }

        return Tenant::findOrFail($this->deletingId)->upcomingBookingsCount();
    }

    /**
     * Permanently remove the portal and everything it owns.
     */
    public function deletePortal(): void
    {
        $portal = Tenant::findOrFail($this->deletingId);

        $this->authorize('delete', $portal);

        $portal->delete();

        $this->deletingId = null;

        unset($this->portals, $this->deletingUpcomingCount);

        Flux::modal('portal-delete')->close();
        Flux::toast(variant: 'success', text: __('Portal deleted.'));
    }

    /**
     * The plans a portal can be put on.
     *
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::query()->orderBy('position')->orderBy('name')->get();
    }

    /**
     * The timezones offered when configuring a portal.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function timezones(): array
    {
        return Timezones::options();
    }
}; ?>

<x-slot:title>{{ __('Portals') }}</x-slot>

<div class="w-full">
    <div class="flex w-full flex-col gap-6">
        <x-page-header :heading="__('Portals')" :subheading="__('Every business using the platform has its own portal and one manager account.')">
            <x-slot:actions>
                <flux:button variant="primary" icon="plus" wire:click="createPortal">
                    {{ __('New portal') }}
                </flux:button>
            </x-slot:actions>
        </x-page-header>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by name, slug or email')"
                class="sm:max-w-sm"
                clearable
            />

            <flux:select wire:model.live="status" class="sm:max-w-40">
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="suspended">{{ __('Suspended') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
            @if ($this->portals->isEmpty())
                <div class="flex flex-col items-center gap-3 px-6 py-16 text-center">
                    <flux:icon name="building-office-2" class="size-10 text-zinc-400" />
                    <flux:heading size="lg">{{ __('No portals yet') }}</flux:heading>
                    <flux:text>{{ __('Create the first portal to onboard a business.') }}</flux:text>
                    <flux:button class="mt-2" variant="primary" icon="plus" wire:click="createPortal">
                        {{ __('New portal') }}
                    </flux:button>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Portal') }}</flux:table.column>
                        <flux:table.column>{{ __('Manager') }}</flux:table.column>
                        <flux:table.column>{{ __('Services') }}</flux:table.column>
                        <flux:table.column>{{ __('People') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->portals as $portal)
                            <flux:table.row wire:key="portal-{{ $portal->id }}">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        @if ($portal->logoUrl())
                                            <img src="{{ $portal->logoUrl() }}" alt="{{ $portal->name }}" class="size-9 shrink-0 rounded-lg object-cover">
                                        @else
                                            <span
                                                class="flex size-9 shrink-0 items-center justify-center rounded-lg text-xs font-semibold text-white"
                                                style="background-color: {{ $portal->brand_color }}"
                                            >{{ $portal->initials() }}</span>
                                        @endif

                                        <div class="min-w-0">
                                            <a href="{{ route('admin.portals.show', $portal) }}" wire:navigate class="block truncate font-medium hover:underline">
                                                {{ $portal->name }}
                                            </a>
                                            <flux:text size="sm" class="truncate">/p/{{ $portal->slug }}</flux:text>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if ($portal->manager)
                                        <div class="min-w-0">
                                            <div class="truncate">{{ $portal->manager->name }}</div>
                                            <flux:text size="sm" class="truncate">{{ $portal->manager->email }}</flux:text>
                                        </div>
                                    @else
                                        <flux:badge size="sm" color="amber" inset="top bottom">{{ __('Not assigned') }}</flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell class="tabular-nums">{{ $portal->services_count }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $portal->users_count }}</flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$portal->is_active ? 'emerald' : 'zinc'" inset="top bottom">
                                        {{ $portal->is_active ? __('Active') : __('Suspended') }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell align="end">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" inset="top bottom" />

                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="editPortal('{{ $portal->slug }}')">
                                                {{ __('Edit') }}
                                            </flux:menu.item>
                                            <flux:menu.item icon="eye" :href="route('admin.portals.show', $portal)" wire:navigate>
                                                {{ __('Details') }}
                                            </flux:menu.item>
                                            <flux:menu.item icon="arrow-top-right-on-square" :href="$portal->publicUrl()" target="_blank">
                                                {{ __('Public page') }}
                                            </flux:menu.item>

                                            <flux:menu.separator />

                                            <flux:menu.item :icon="$portal->is_active ? 'pause-circle' : 'play-circle'" wire:click="toggleActive('{{ $portal->slug }}')">
                                                {{ $portal->is_active ? __('Suspend') : __('Activate') }}
                                            </flux:menu.item>
                                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $portal->id }})">
                                                {{ __('Delete') }}
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>

        @if ($this->portals->hasPages())
            {{ $this->portals->links() }}
        @endif
    </div>

    <flux:modal name="portal-form" class="w-full max-w-2xl" @close="$wire.resetValidation()">
        <form wire:submit="savePortal" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $this->form->portal ? __('Edit portal') : __('New portal') }}
                </flux:heading>
                <flux:subheading>
                    {{ $this->form->portal ? __('Update the details of this business.') : __('Provision a portal and its single manager account.') }}
                </flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model.live.debounce.500ms="form.name" :label="__('Business name')" required autofocus />
                <flux:input wire:model="form.slug" :label="__('Public slug')" required :description="__('Used in the public link.')" />
                <flux:input wire:model="form.contact_email" type="email" :label="__('Contact email')" />
                <flux:input wire:model="form.contact_phone" :label="__('Contact phone')" />
                <flux:input wire:model="form.website" type="url" :label="__('Website')" />
                <flux:input wire:model="form.address" :label="__('Address')" />

                <flux:select wire:model="form.timezone" :label="__('Timezone')">
                    @foreach ($this->timezones as $timezone)
                        <flux:select.option :value="$timezone">{{ $timezone }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.locale" :label="__('Default language')">
                    <flux:select.option value="it">Italiano</flux:select.option>
                    <flux:select.option value="en">English</flux:select.option>
                </flux:select>

                <flux:input wire:model="form.brand_color" type="color" :label="__('Brand color')" class="sm:max-w-40" />

                <flux:select wire:model="form.plan_id" :label="__('Plan')">
                    <flux:select.option value="">{{ __('No plan') }}</flux:select.option>
                    @foreach ($this->plans as $plan)
                        <flux:select.option :value="$plan->id">{{ $plan->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:textarea wire:model="form.description" :label="__('Short description')" rows="2" />

            @if ($this->form->portal === null)
                <flux:separator :text="__('Manager account')" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.manager_name" :label="__('Manager name')" required />
                    <flux:input wire:model="form.manager_email" type="email" :label="__('Manager email')" required />
                </div>

                <flux:callout icon="key" variant="secondary">
                    {{ __('A password will be generated automatically and shown once after creation.') }}
                </flux:callout>
            @endif

            <flux:switch wire:model="form.is_active" :label="__('Portal active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">
                    {{ $this->form->portal ? __('Save changes') : __('Create portal') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="manager-credentials" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Portal created') }}</flux:heading>
                <flux:subheading>{{ __('Share these credentials with the business. The password is shown only once.') }}</flux:subheading>
            </div>

            <flux:input readonly :label="__('Email')" :value="$generatedEmail" copyable />
            <flux:input readonly :label="__('Temporary password')" :value="$generatedPassword" copyable />

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary" type="button">{{ __('Done') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="portal-delete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this portal?') }}</flux:heading>
                <flux:subheading>{{ __('Manager, operators, services and availability of this portal will be permanently removed.') }}</flux:subheading>
            </div>

            @if ($this->deletingUpcomingCount > 0)
                <flux:callout icon="exclamation-triangle" variant="danger">
                    {{ __('This portal still has :count upcoming bookings: they will be deleted too, and no one will be notified.', ['count' => $this->deletingUpcomingCount]) }}
                </flux:callout>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deletePortal">{{ __('Delete portal') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
