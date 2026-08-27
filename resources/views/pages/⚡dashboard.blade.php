<?php

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    /**
     * Platform wide counters shown to administrators.
     *
     * @return array{portals: int, active_portals: int, managers: int, operators: int}
     */
    #[Computed]
    public function platformStats(): array
    {
        return [
            'portals' => Tenant::query()->count(),
            'active_portals' => Tenant::query()->active()->count(),
            'managers' => User::query()->role(UserRole::Manager)->count(),
            'operators' => User::query()->role(UserRole::Default)->whereNotNull('tenant_id')->count(),
        ];
    }

    /**
     * The most recently provisioned portals.
     *
     * @return Collection<int, Tenant>
     */
    #[Computed]
    public function recentPortals(): Collection
    {
        return Tenant::query()
            ->withCount(['services', 'users'])
            ->with('manager')
            ->latest()
            ->take(5)
            ->get();
    }

    /**
     * Counters shown to a portal manager.
     *
     * @return array{services: int, staff: int, upcoming: int, pending: int}
     */
    #[Computed]
    public function portalStats(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'services' => Service::query()->where('tenant_id', $tenantId)->active()->count(),
            'staff' => User::query()->where('tenant_id', $tenantId)->bookable()->count(),
            'upcoming' => Booking::query()->forTenant($tenantId)->blocking()->upcoming()->count(),
            'pending' => Booking::query()->forTenant($tenantId)->where('status', BookingStatus::Pending)->upcoming()->count(),
        ];
    }

    /**
     * The weekly availability of the authenticated staff member.
     *
     * @return Collection<int, \App\Models\AvailabilityRule>
     */
    #[Computed]
    public function myWeek(): Collection
    {
        return auth()->user()->availabilityRules()->orderBy('weekday')->orderBy('starts_at')->get();
    }

    /**
     * The next bookings of the authenticated staff member.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function myNextBookings(): Collection
    {
        return Booking::query()
            ->where('user_id', auth()->id())
            ->blocking()
            ->upcoming()
            ->with('service')
            ->orderBy('starts_at')
            ->take(5)
            ->get();
    }

    /**
     * The services the authenticated operator is assigned to.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function myServices(): Collection
    {
        return auth()->user()->services()->active()->orderBy('name')->get();
    }
}; ?>

<x-slot:title>{{ __('Dashboard') }}</x-slot>

    <div class="flex w-full flex-col gap-8">
        @php($user = auth()->user())

        <x-page-header
            :heading="__('Hi :name', ['name' => $user->name])"
            :subheading="$user->tenant?->name ?? __('Platform administration')"
        />

        @if ($user->isAdmin())
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card icon="building-office-2" :label="__('Portals')" :value="$this->platformStats['portals']" />
                <x-stat-card icon="check-badge" :label="__('Active portals')" :value="$this->platformStats['active_portals']" />
                <x-stat-card icon="identification" :label="__('Managers')" :value="$this->platformStats['managers']" />
                <x-stat-card icon="user-group" :label="__('Operators')" :value="$this->platformStats['operators']" />
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Latest portals') }}</flux:heading>
                    <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" :href="route('admin.portals')" wire:navigate>
                        {{ __('Manage') }}
                    </flux:button>
                </div>

                @if ($this->recentPortals->isEmpty())
                    <div class="px-5 py-10 text-center">
                        <flux:text>{{ __('No portal has been created yet.') }}</flux:text>
                    </div>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Portal') }}</flux:table.column>
                            <flux:table.column>{{ __('Manager') }}</flux:table.column>
                            <flux:table.column>{{ __('Services') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->recentPortals as $portal)
                                <flux:table.row :key="$portal->id">
                                    <flux:table.cell>
                                        <a href="{{ route('admin.portals.show', $portal) }}" wire:navigate class="font-medium hover:underline">
                                            {{ $portal->name }}
                                        </a>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $portal->manager?->email ?? __('Not assigned') }}</flux:table.cell>
                                    <flux:table.cell>{{ $portal->services_count }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm" :color="$portal->is_active ? 'emerald' : 'zinc'" inset="top bottom">
                                            {{ $portal->is_active ? __('Active') : __('Suspended') }}
                                        </flux:badge>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        @elseif ($user->isManager())
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card icon="calendar-days" :label="__('Upcoming bookings')" :value="$this->portalStats['upcoming']" />
                <x-stat-card icon="clock" :label="__('Waiting for approval')" :value="$this->portalStats['pending']" :hint="$this->portalStats['pending'] > 0 ? __('Review them in Bookings') : null" />
                <x-stat-card icon="squares-2x2" :label="__('Published services')" :value="$this->portalStats['services']" />
                <x-stat-card icon="user-group" :label="__('Bookable people')" :value="$this->portalStats['staff']" />
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Get started') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Three steps and your portal is ready to take bookings.') }}</flux:text>

                    <div class="mt-4 space-y-3">
                        <flux:button class="w-full justify-start" variant="subtle" icon="squares-2x2" :href="route('app.services')" wire:navigate>
                            {{ __('Create your services') }}
                        </flux:button>
                        <flux:button class="w-full justify-start" variant="subtle" icon="user-group" :href="route('app.staff')" wire:navigate>
                            {{ __('Add your operators') }}
                        </flux:button>
                        <flux:button class="w-full justify-start" variant="subtle" icon="clock" :href="route('app.availability')" wire:navigate>
                            {{ __('Set weekly availability') }}
                        </flux:button>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Share your portal') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Send this link to your customers or embed it in your website.') }}</flux:text>

                    <flux:input class="mt-4" readonly :value="$user->tenant->publicUrl()" copyable />

                    <flux:button class="mt-3" variant="primary" icon-trailing="arrow-top-right-on-square" :href="$user->tenant->publicUrl()" target="_blank">
                        {{ __('Open public page') }}
                    </flux:button>
                </div>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-card icon="calendar-days" :label="__('Upcoming bookings')" :value="$this->myNextBookings->count()" />
                <x-stat-card icon="clock" :label="__('Weekly slots')" :value="$this->myWeek->count()" />
                <x-stat-card icon="squares-2x2" :label="__('My services')" :value="$this->myServices->count()" />
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Your next bookings') }}</flux:heading>
                    <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" :href="route('app.bookings')" wire:navigate>
                        {{ __('See all') }}
                    </flux:button>
                </div>

                @if ($this->myNextBookings->isEmpty())
                    <div class="px-5 py-10 text-center">
                        <flux:text>{{ __('Nothing booked yet.') }}</flux:text>
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->myNextBookings as $booking)
                            @php($start = $booking->startsAtIn($user->timezone))

                            <div class="flex items-center justify-between gap-4 px-5 py-3" wire:key="next-{{ $booking->id }}">
                                <div class="flex items-center gap-3">
                                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $booking->service->color }}"></span>
                                    <div>
                                        <div class="font-medium">{{ $booking->customer_name }}</div>
                                        <flux:text size="sm">{{ $booking->service->name }}</flux:text>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <div class="font-medium capitalize">{{ $start->translatedFormat('D d M') }}</div>
                                    <flux:text size="sm" class="tabular-nums">{{ $start->format('H:i') }}</flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Your week') }}</flux:heading>

                @if ($this->myWeek->isEmpty())
                    <flux:text class="mt-2">{{ __('You have not set any working hours yet.') }}</flux:text>
                    <flux:button class="mt-4" variant="primary" icon="clock" :href="route('app.availability')" wire:navigate>
                        {{ __('Set weekly availability') }}
                    </flux:button>
                @else
                    <div class="mt-4 space-y-2">
                        @foreach ($this->myWeek as $rule)
                            <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-4 py-2 dark:bg-zinc-800/60" wire:key="rule-{{ $rule->id }}">
                                <flux:text class="font-medium">{{ \App\Support\Week::label($rule->weekday) }}</flux:text>
                                <flux:text class="tabular-nums">{{ substr($rule->starts_at, 0, 5) }} – {{ substr($rule->ends_at, 0, 5) }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
