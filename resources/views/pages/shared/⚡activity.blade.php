<?php

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Search;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Register')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $group = 'all';

    #[Url(except: 'all')]
    public string $portal = 'all';

    #[Url(except: 'all')]
    public string $actor = 'all';

    #[Url(as: 'from', except: '')]
    public string $fromDate = '';

    #[Url(as: 'to', except: '')]
    public string $toDate = '';

    /**
     * The register is for the platform administrator and the portal manager.
     *
     * An operator has their own agenda; who did what across the business is
     * not theirs to read.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isManager(), 403);
    }

    /**
     * Determine whether the reader sees every portal.
     */
    #[Computed]
    public function seesEverything(): bool
    {
        return auth()->user()->isAdmin();
    }

    /**
     * The lines matching the filters.
     *
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->with(['tenant', 'user'])
            ->unless($this->seesEverything, fn (Builder $query) => $query->forTenant(auth()->user()->tenant_id))
            ->when($this->seesEverything && $this->portal !== 'all', fn (Builder $query) => $query->where('tenant_id', $this->portal))
            ->when($this->group !== 'all', fn (Builder $query) => $query->where('action', 'like', $this->group.'.%'))
            ->when($this->actor !== 'all', fn (Builder $query) => $query->where('user_id', $this->actor))
            ->when($this->fromDate !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->toDate))
            ->when($this->search !== '', fn (Builder $query) => Search::contains($query, ['actor_name', 'action', 'subject_label'], $this->search))
            ->latest('created_at')
            ->latest('id')
            ->paginate(30);
    }

    /**
     * The families of action on offer in the filter.
     *
     * Read from what is actually in the register: a filter listing kinds of
     * event that never happened is noise.
     *
     * @return Collection<int, string>
     */
    #[Computed]
    public function groups(): Collection
    {
        return ActivityLog::query()
            ->unless($this->seesEverything, fn (Builder $query) => $query->forTenant(auth()->user()->tenant_id))
            ->distinct()
            ->pluck('action')
            ->map(fn (string $action) => str($action)->before('.')->value())
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * The people who left a trace, for the filter.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function actors(): Collection
    {
        $ids = ActivityLog::query()
            ->unless($this->seesEverything, fn (Builder $query) => $query->forTenant(auth()->user()->tenant_id))
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        return User::query()->whereKey($ids)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * The portals to filter by, for the administrator.
     *
     * @return Collection<int, Tenant>
     */
    #[Computed]
    public function portals(): Collection
    {
        return $this->seesEverything
            ? Tenant::query()->orderBy('name')->get(['id', 'name'])
            : collect();
    }

    /**
     * Reset pagination whenever a filter changes.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'group', 'portal', 'actor', 'fromDate', 'toDate'], true)) {
            $this->resetPage();
        }
    }

    /**
     * The wording of an action, with a fallback for the ones added later.
     */
    public function actionLabel(string $action): string
    {
        return match ($action) {
            'booking.created' => __('Booking created'),
            'booking.confirmed' => __('Booking confirmed'),
            'booking.cancelled' => __('Booking cancelled'),
            'booking.rescheduled' => __('Booking moved'),
            'booking.outcome' => __('Outcome recorded'),
            'customer.erased' => __('Customer data erased'),
            'service.created' => __('Service created'),
            'service.updated' => __('Service updated'),
            'service.deleted' => __('Service deleted'),
            'resource.created' => __('Resource created'),
            'resource.updated' => __('Resource updated'),
            'resource.deleted' => __('Resource deleted'),
            'resource.blocked' => __('Resource made unavailable'),
            'resource.unblocked' => __('Resource unavailability removed'),
            'staff.deleted' => __('Staff member deleted'),
            'account.deleted' => __('Account deleted'),
            'portal.updated' => __('Portal settings changed'),
            'portal.closed' => __('Portal closed for a period'),
            'portal.exported' => __('Portal data exported'),
            'portal.deleted' => __('Portal deleted'),
            'plan.created' => __('Plan created'),
            'plan.updated' => __('Plan updated'),
            'impersonation.started' => __('Impersonation started'),
            'impersonation.stopped' => __('Impersonation ended'),
            'impersonation.expired' => __('Impersonation expired'),
            'job.failed' => __('Job failed'),
            'job.reported' => __('Failures reported'),
            'auth.login' => __('Signed in'),
            'auth.logout' => __('Signed out'),
            'auth.failed' => __('Failed sign in'),
            default => $action,
        };
    }
}; ?>

<x-slot:title>{{ __('Register') }}</x-slot>

<div class="w-full">
    @php($timezone = auth()->user()->timezone)

    <div class="flex w-full flex-col gap-6">
        <x-page-header
            :heading="__('Register')"
            :subheading="$this->seesEverything ? __('Every action taken on the platform.') : __('Every action taken inside your portal.')"
        />

        <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :label="__('Search')"
                :placeholder="__('Search by person or action')"
                class="lg:max-w-xs"
                clearable
            />

            <flux:select wire:model.live="group" :label="__('Kind')" class="lg:max-w-44">
                <flux:select.option value="all">{{ __('All kinds') }}</flux:select.option>
                @foreach ($this->groups as $group)
                    <flux:select.option :value="$group">{{ ucfirst($group) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="actor" :label="__('Person')" class="lg:max-w-52">
                <flux:select.option value="all">{{ __('Everyone') }}</flux:select.option>
                @foreach ($this->actors as $person)
                    <flux:select.option :value="$person->id">{{ $person->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->seesEverything)
                <flux:select wire:model.live="portal" :label="__('Portal')" class="lg:max-w-52">
                    <flux:select.option value="all">{{ __('All portals') }}</flux:select.option>
                    @foreach ($this->portals as $item)
                        <flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div class="flex items-end gap-2">
                <flux:input wire:model.live="fromDate" type="date" size="sm" :label="__('From date')" class="max-w-40" />
                <span class="pb-2 text-zinc-400">–</span>
                <flux:input wire:model.live="toDate" type="date" size="sm" :label="__('To date')" class="max-w-40" />
            </div>
        </div>

        @if ($this->entries->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-zinc-300 px-6 py-20 text-center dark:border-zinc-700">
                <flux:icon name="clipboard-document-list" class="size-10 text-zinc-400" />
                <flux:heading size="lg">{{ __('Nothing in the register') }}</flux:heading>
                <flux:text>{{ __('Nothing matches these filters yet.') }}</flux:text>
            </div>
        @else
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('When') }}</flux:table.column>
                        <flux:table.column>{{ __('Who') }}</flux:table.column>
                        <flux:table.column>{{ __('What') }}</flux:table.column>
                        @if ($this->seesEverything)
                            <flux:table.column>{{ __('Portal') }}</flux:table.column>
                        @endif
                        <flux:table.column>{{ __('Details') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->entries as $entry)
                            <flux:table.row wire:key="log-{{ $entry->id }}">
                                <flux:table.cell class="tabular-nums">
                                    {{ $entry->created_at?->setTimezone($timezone)->translatedFormat('d M Y') }}
                                    <flux:text size="sm" class="tabular-nums">
                                        {{ $entry->created_at?->setTimezone($timezone)->format('H:i') }}
                                    </flux:text>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="min-w-0">
                                        <div class="truncate font-medium">{{ $entry->actor_name }}</div>
                                        @if ($entry->actor_role)
                                            <flux:text size="sm">{{ $entry->actor_role }}</flux:text>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="min-w-0">
                                        <div class="truncate">{{ $this->actionLabel($entry->action) }}</div>
                                        @if ($entry->subject_label)
                                            <flux:text size="sm" class="truncate">{{ $entry->subject_label }}</flux:text>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                @if ($this->seesEverything)
                                    <flux:table.cell>{{ $entry->tenant?->name ?? '—' }}</flux:table.cell>
                                @endif

                                <flux:table.cell class="whitespace-normal">
                                    <flux:text size="sm">
                                        @foreach ($entry->context ?? [] as $key => $value)
                                            <span class="me-2">{{ $key }}: {{ is_array($value) ? implode(', ', $value) : $value }}</span>
                                        @endforeach

                                        @if ($entry->ip_address)
                                            <span class="text-zinc-400">{{ $entry->ip_address }}</span>
                                        @endif
                                    </flux:text>
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
