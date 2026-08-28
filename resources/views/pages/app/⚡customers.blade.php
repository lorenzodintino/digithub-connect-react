<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Csv;
use App\Support\Search;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Customers')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Locked]
    public ?int $openId = null;

    public string $notes = '';

    /**
     * Load the page for the manager of the portal.
     */
    public function mount(): void
    {
        $this->authorize('viewAny', Customer::class);
    }

    /**
     * The portal the anagraphic belongs to.
     */
    #[Computed]
    public function tenant(): Tenant
    {
        return auth()->user()->tenant;
    }

    /**
     * The customers of the portal, with how much they booked.
     *
     * @return LengthAwarePaginator<int, Customer>
     */
    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        return Customer::query()
            ->forTenant($this->tenant)
            ->withCount([
                'bookings',
                'bookings as no_show_count' => fn ($query) => $query->where('status', BookingStatus::NoShow),
                'bookings as upcoming_count' => fn ($query) => $query->blocking()->upcoming(),
            ])
            ->withMax('bookings as last_booking_at', 'starts_at')
            ->when($this->search !== '', fn ($query) => Search::contains($query, ['name', 'email', 'phone'], $this->search))
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * Hand the directory over as a CSV file, search included.
     */
    public function exportCsv(): StreamedResponse
    {
        $this->authorize('viewAny', Customer::class);

        $timezone = auth()->user()->timezone;

        $customers = Customer::query()
            ->forTenant($this->tenant)
            ->withCount([
                'bookings',
                'bookings as no_show_count' => fn ($query) => $query->where('status', BookingStatus::NoShow),
            ])
            ->withMax('bookings as last_booking_at', 'starts_at')
            ->when($this->search !== '', fn ($query) => Search::contains($query, ['name', 'email', 'phone'], $this->search))
            ->orderBy('name')
            ->lazy();

        $headings = [__('Name'), __('Email'), __('Phone'), __('Bookings'), __('No shows'), __('Last booking'), __('Internal note')];

        $rows = $customers->map(fn (Customer $customer) => [
            $customer->name,
            $customer->email,
            $customer->phone,
            $customer->bookings_count,
            $customer->no_show_count,
            $customer->last_booking_at
                ? \Carbon\CarbonImmutable::parse($customer->last_booking_at)->setTimezone($timezone)->format('d/m/Y')
                : '',
            $customer->notes,
        ]);

        return Csv::download(Csv::fileName(__('Customers'), $this->tenant->name), $headings, $rows);
    }

    /**
     * The customer whose card is open, if any.
     */
    #[Computed]
    public function openCustomer(): ?Customer
    {
        return $this->openId === null ? null : Customer::find($this->openId);
    }

    /**
     * Everything the open customer ever booked, most recent first.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function history(): Collection
    {
        if ($this->openCustomer === null) {
            return collect();
        }

        return $this->openCustomer->bookings()
            ->with(['service.tenant', 'operator'])
            ->orderByDesc('starts_at')
            ->limit(50)
            ->get();
    }

    /**
     * Open the card of a customer.
     */
    public function showCustomer(int $customerId): void
    {
        $customer = Customer::findOrFail($customerId);

        $this->authorize('view', $customer);

        $this->openId = $customer->id;
        $this->notes = $customer->notes ?? '';

        unset($this->openCustomer, $this->history);

        Flux::modal('customer-card')->show();
    }

    /**
     * Save the note the portal keeps about this customer.
     */
    public function saveNotes(): void
    {
        $customer = Customer::findOrFail($this->openId);

        $this->authorize('update', $customer);

        $this->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        $customer->update(['notes' => $this->notes ?: null]);

        unset($this->customers, $this->openCustomer);

        Flux::toast(variant: 'success', text: __('Note saved.'));
    }

    /**
     * Reset pagination whenever the search changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }
}; ?>

<x-slot:title>{{ __('Customers') }}</x-slot>

<div class="w-full">
    @php($timezone = auth()->user()->timezone)

    <div class="flex w-full flex-col gap-6">
        <x-page-header
            :heading="__('Customers')"
            :subheading="__('Everybody who ever booked with your portal, and what they booked.')"
        />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by name, email or phone')"
                class="max-w-sm"
                clearable
            />

            <flux:button size="sm" variant="subtle" icon="arrow-down-tray" wire:click="exportCsv">
                {{ __('Export CSV') }}
            </flux:button>
        </div>

        @if ($this->customers->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-20 text-center dark:border-zinc-700">
                <flux:icon name="identification" class="size-10 text-zinc-400" />
                <flux:heading size="lg">{{ __('No customer yet') }}</flux:heading>
                <flux:text>{{ __('They appear here as soon as somebody books.') }}</flux:text>
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Customer') }}</flux:table.column>
                        <flux:table.column>{{ __('Phone') }}</flux:table.column>
                        <flux:table.column>{{ __('Bookings') }}</flux:table.column>
                        <flux:table.column>{{ __('No shows') }}</flux:table.column>
                        <flux:table.column>{{ __('Last booking') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->customers as $customer)
                            <flux:table.row wire:key="customer-{{ $customer->id }}">
                                <flux:table.cell>
                                    <div class="min-w-0">
                                        <div class="truncate font-medium">{{ $customer->name }}</div>
                                        <flux:text size="sm" class="truncate">{{ $customer->email }}</flux:text>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="tabular-nums">{{ $customer->phone ?? '—' }}</flux:table.cell>

                                <flux:table.cell class="tabular-nums">
                                    {{ $customer->bookings_count }}

                                    @if ($customer->upcoming_count > 0)
                                        <flux:badge size="sm" color="emerald" inset="top bottom" class="ms-1">
                                            {{ __(':count upcoming', ['count' => $customer->upcoming_count]) }}
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell class="tabular-nums">
                                    @if ($customer->no_show_count > 0)
                                        <flux:badge size="sm" color="orange" inset="top bottom">{{ $customer->no_show_count }}</flux:badge>
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell class="tabular-nums">
                                    {{ $customer->last_booking_at
                                        ? \Carbon\CarbonImmutable::parse($customer->last_booking_at)->setTimezone($timezone)->translatedFormat('d M Y')
                                        : '—' }}
                                </flux:table.cell>

                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="ghost" icon="arrow-right" wire:click="showCustomer({{ $customer->id }})">
                                        {{ __('Open') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            @if ($this->customers->hasPages())
                {{ $this->customers->links() }}
            @endif
        @endif
    </div>

    <flux:modal name="customer-card" class="w-full max-w-2xl" variant="flyout">
        @if ($this->openCustomer)
            @php($customer = $this->openCustomer)

            <div class="space-y-6">
                <div class="pe-8">
                    <flux:heading size="lg">{{ $customer->name }}</flux:heading>
                    <flux:subheading>
                        {{ $customer->email }}
                        @if ($customer->phone)
                            · {{ $customer->phone }}
                        @endif
                    </flux:subheading>
                </div>

                <form wire:submit="saveNotes" class="space-y-3">
                    <flux:textarea
                        wire:model="notes"
                        :label="__('Internal note')"
                        :description="__('Only your portal sees this. The customer never does.')"
                        rows="3"
                    />

                    <div class="flex justify-end">
                        <flux:button size="sm" variant="primary" type="submit">{{ __('Save note') }}</flux:button>
                    </div>
                </form>

                <flux:separator :text="__('History')" />

                <div class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($this->history as $booking)
                        <div class="flex items-center gap-3 py-3" wire:key="history-{{ $booking->id }}">
                            <span class="w-28 shrink-0 text-sm tabular-nums">
                                {{ $booking->startsAtIn($timezone)->translatedFormat('d M Y') }}
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $booking->startsAtIn($timezone)->format('H:i') }}
                                </span>
                            </span>

                            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $booking->service->displayColor() }}"></span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $booking->service->name }}</span>
                                <span class="block truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $booking->operator->name }}</span>
                            </span>

                            <flux:badge size="sm" :color="$booking->status->color()" class="shrink-0">
                                {{ $booking->status->label() }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="filled" type="button">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
