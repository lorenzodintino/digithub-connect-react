<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Search;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Accounts')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $role = 'all';

    #[Url(except: 'all')]
    public string $portal = 'all';

    public ?string $generatedPassword = null;

    public ?string $generatedEmail = null;

    public ?int $deletingId = null;

    /**
     * The accounts matching the current filters.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function accounts(): LengthAwarePaginator
    {
        return User::query()
            ->with('tenant')
            ->when($this->search !== '', fn ($query) => Search::contains($query, ['name', 'email'], $this->search))
            ->when($this->role !== 'all', fn ($query) => $query->where('role', $this->role))
            ->when($this->portal !== 'all', fn ($query) => $query->where('tenant_id', $this->portal))
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * The portals available in the filter dropdown.
     *
     * @return Collection<int, Tenant>
     */
    #[Computed]
    public function portals(): Collection
    {
        return Tenant::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Reset pagination whenever a filter changes.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'portal'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Toggle the active state of the given account.
     */
    public function toggleActive(User $account): void
    {
        $this->authorize('update', $account);

        abort_if($account->is(auth()->user()), 403);

        $account->update(['is_active' => ! $account->is_active]);

        unset($this->accounts);

        Flux::toast(variant: 'success', text: $account->is_active ? __('Account enabled.') : __('Account disabled.'));
    }

    /**
     * Issue a new temporary password for the given account.
     */
    public function resetPassword(User $account): void
    {
        $this->authorize('update', $account);

        $password = Str::password(14, symbols: false);

        $account->update(['password' => $password]);

        $this->generatedEmail = $account->email;
        $this->generatedPassword = $password;

        Flux::modal('account-password')->show();
    }

    /**
     * Ask for confirmation before removing an account.
     */
    public function confirmDelete(int $accountId): void
    {
        $this->deletingId = $accountId;

        unset($this->deletingUpcomingCount);

        Flux::modal('account-delete')->show();
    }

    /**
     * How many upcoming bookings the account being deleted still holds.
     */
    #[Computed]
    public function deletingUpcomingCount(): int
    {
        if ($this->deletingId === null) {
            return 0;
        }

        return User::findOrFail($this->deletingId)->upcomingBookingsCount();
    }

    /**
     * Permanently remove the selected account.
     */
    public function deleteAccount(): void
    {
        $account = User::findOrFail($this->deletingId);

        $this->authorize('delete', $account);

        // Deleting cascades to their bookings, so refuse while any is still live.
        $upcoming = $account->upcomingBookingsCount();

        if ($upcoming > 0) {
            $this->deletingId = null;

            unset($this->deletingUpcomingCount);

            Flux::modal('account-delete')->close();
            Flux::toast(variant: 'danger', text: __('This account still has :count upcoming bookings. Cancel or reassign them first.', ['count' => $upcoming]));

            return;
        }

        $account->delete();

        $this->deletingId = null;

        unset($this->accounts, $this->deletingUpcomingCount);

        Flux::modal('account-delete')->close();
        Flux::toast(variant: 'success', text: __('Account deleted.'));
    }
}; ?>

<x-slot:title>{{ __('Accounts') }}</x-slot>

<div class="w-full">
    <div class="flex w-full flex-col gap-6">
        <x-page-header :heading="__('Accounts')" :subheading="__('Every account on the platform, across all portals.')" />

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by name or email')"
                class="sm:max-w-sm"
                clearable
            />

            <flux:select wire:model.live="role" class="sm:max-w-44">
                <flux:select.option value="all">{{ __('All roles') }}</flux:select.option>
                @foreach (UserRole::cases() as $case)
                    <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="portal" class="sm:max-w-56">
                <flux:select.option value="all">{{ __('All portals') }}</flux:select.option>
                @foreach ($this->portals as $portal)
                    <flux:select.option :value="$portal->id">{{ $portal->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
            @if ($this->accounts->isEmpty())
                <div class="px-6 py-16 text-center">
                    <flux:text>{{ __('No account matches these filters.') }}</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column>{{ __('Portal') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->accounts as $account)
                            <flux:table.row wire:key="account-{{ $account->id }}">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <flux:avatar size="xs" :name="$account->name" :initials="$account->initials()" />
                                        <div class="min-w-0">
                                            <div class="truncate font-medium">{{ $account->name }}</div>
                                            <flux:text size="sm" class="truncate">{{ $account->email }}</flux:text>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$account->role->color()" inset="top bottom">
                                        {{ $account->role->label() }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell>{{ $account->tenant?->name ?? '—' }}</flux:table.cell>

                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$account->is_active ? 'emerald' : 'zinc'" inset="top bottom">
                                        {{ $account->is_active ? __('Active') : __('Disabled') }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell align="end">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" inset="top bottom" />

                                        <flux:menu>
                                            <flux:menu.item icon="key" wire:click="resetPassword({{ $account->id }})">
                                                {{ __('Issue new password') }}
                                            </flux:menu.item>

                                            @unless ($account->is(auth()->user()))
                                                <flux:menu.item :icon="$account->is_active ? 'no-symbol' : 'check-circle'" wire:click="toggleActive({{ $account->id }})">
                                                    {{ $account->is_active ? __('Disable') : __('Enable') }}
                                                </flux:menu.item>

                                                <flux:menu.separator />

                                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $account->id }})">
                                                    {{ __('Delete') }}
                                                </flux:menu.item>
                                            @endunless
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>

        @if ($this->accounts->hasPages())
            {{ $this->accounts->links() }}
        @endif
    </div>

    <flux:modal name="account-password" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New password issued') }}</flux:heading>
                <flux:subheading>{{ __('Share it with the account owner. It is shown only once.') }}</flux:subheading>
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

    <flux:modal name="account-delete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete this account?') }}</flux:heading>
                <flux:subheading>{{ __('The account and its availability will be permanently removed.') }}</flux:subheading>
            </div>

            @if ($this->deletingUpcomingCount > 0)
                <flux:callout icon="exclamation-triangle" variant="danger">
                    {{ __('This account still has :count upcoming bookings. Cancel or reassign them first.', ['count' => $this->deletingUpcomingCount]) }}
                </flux:callout>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button
                    variant="danger"
                    wire:click="deleteAccount"
                    :disabled="$this->deletingUpcomingCount > 0"
                >{{ __('Delete account') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
