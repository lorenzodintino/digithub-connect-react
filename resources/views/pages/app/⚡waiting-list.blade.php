<?php

use App\Models\Tenant;
use App\Models\WaitingListEntry;
use App\Support\Search;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Waiting list')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /**
     * Only the manager runs the waiting list of the portal.
     */
    public function mount(): void
    {
        $this->authorize('update', $this->tenant);
    }

    /**
     * The portal the list belongs to.
     */
    #[Computed]
    public function tenant(): Tenant
    {
        return auth()->user()->tenant;
    }

    /**
     * The people waiting, the soonest window first.
     *
     * @return LengthAwarePaginator<int, WaitingListEntry>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return WaitingListEntry::query()
            ->forTenant($this->tenant)
            ->with('service')
            ->when($this->search !== '', fn ($query) => Search::contains($query, ['customer_name', 'customer_email', 'customer_phone'], $this->search))
            ->orderBy('wanted_from')
            ->paginate(20);
    }

    /**
     * Take somebody off the list on their behalf.
     */
    public function remove(int $entryId): void
    {
        $this->authorize('update', $this->tenant);

        WaitingListEntry::query()->forTenant($this->tenant)->whereKey($entryId)->delete();

        unset($this->entries);

        Flux::toast(variant: 'success', text: __('Removed from the waiting list.'));
    }

    /**
     * Reset pagination whenever the search changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }
}; ?>

<x-slot:title>{{ __('Waiting list') }}</x-slot>

<div class="w-full">
    @php($timezone = auth()->user()->timezone)

    <div class="flex w-full flex-col gap-6">
        <x-page-header
            :heading="__('Waiting list')"
            :subheading="__('People who asked to be told when a place opens up.')"
        />

        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :placeholder="__('Search by name, email or phone')"
            class="max-w-sm"
            clearable
        />

        @if ($this->entries->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-20 text-center dark:border-zinc-700">
                <flux:icon name="bell-alert" class="size-10 text-zinc-400" />
                <flux:heading size="lg">{{ __('Nobody is waiting') }}</flux:heading>
                <flux:text>{{ __('Customers land here when your calendar has nothing that suits them.') }}</flux:text>
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Customer') }}</flux:table.column>
                        <flux:table.column>{{ __('Service') }}</flux:table.column>
                        <flux:table.column>{{ __('Wanted period') }}</flux:table.column>
                        <flux:table.column>{{ __('Last notified') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->entries as $entry)
                            <flux:table.row wire:key="entry-{{ $entry->id }}">
                                <flux:table.cell>
                                    <div class="min-w-0">
                                        <div class="truncate font-medium">{{ $entry->customer_name }}</div>
                                        <flux:text size="sm" class="truncate">
                                            {{ $entry->customer_email }}
                                            @if ($entry->customer_phone)
                                                · {{ $entry->customer_phone }}
                                            @endif
                                        </flux:text>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>{{ $entry->service->name }}</flux:table.cell>

                                <flux:table.cell class="tabular-nums">
                                    {{ $entry->wanted_from->translatedFormat('d M') }} – {{ $entry->wanted_to->translatedFormat('d M Y') }}
                                </flux:table.cell>

                                <flux:table.cell class="tabular-nums">
                                    {{ $entry->notified_at
                                        ? $entry->notified_at->setTimezone($timezone)->translatedFormat('d M, H:i')
                                        : '—' }}
                                </flux:table.cell>

                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="subtle" icon="trash" wire:click="remove({{ $entry->id }})" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>

            @if ($this->entries->hasPages())
                {{ $this->entries->links() }}
            @endif
        @endif
    </div>
</div>
