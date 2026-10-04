<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        @php($user = auth()->user())

        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Overview')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @if ($user?->isAdmin())
                    <flux:sidebar.group :heading="__('Platform')" class="grid">
                        <flux:sidebar.item icon="building-office-2" :href="route('admin.portals')" :current="request()->routeIs('admin.portals*')" wire:navigate>
                            {{ __('Portals') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>
                            {{ __('Accounts') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="rectangle-stack" :href="route('admin.plans')" :current="request()->routeIs('admin.plans')" wire:navigate>
                            {{ __('Plans') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.activity')" :current="request()->routeIs('admin.activity')" wire:navigate>
                            {{ __('Register') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="queue-list" :href="route('admin.queue')" :current="request()->routeIs('admin.queue')" wire:navigate>
                            {{ __('Queue') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                @if ($user?->isManager())
                    <flux:sidebar.group :heading="__('Portal')" class="grid">
                        <flux:sidebar.item icon="calendar-days" :href="route('app.bookings')" :current="request()->routeIs('app.bookings')" wire:navigate>
                            {{ __('Bookings') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="squares-2x2" :href="route('app.services')" :current="request()->routeIs('app.services')" wire:navigate>
                            {{ __('Services') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="rectangle-stack" :href="route('app.resources')" :current="request()->routeIs('app.resources')" wire:navigate>
                            {{ __('Resources') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="identification" :href="route('app.customers')" :current="request()->routeIs('app.customers')" wire:navigate>
                            {{ __('Customers') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="bell-alert" :href="route('app.waiting-list')" :current="request()->routeIs('app.waiting-list')" wire:navigate>
                            {{ __('Waiting list') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="user-group" :href="route('app.staff')" :current="request()->routeIs('app.staff')" wire:navigate>
                            {{ __('Staff') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" :href="route('app.reports')" :current="request()->routeIs('app.reports')" wire:navigate>
                            {{ __('Reports') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clock" :href="route('app.availability')" :current="request()->routeIs('app.availability')" wire:navigate>
                            {{ __('Availability') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('app.activity')" :current="request()->routeIs('app.activity')" wire:navigate>
                            {{ __('Register') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="cog-6-tooth" :href="route('app.portal')" :current="request()->routeIs('app.portal')" wire:navigate>
                            {{ __('Portal settings') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                @if ($user?->isOperator() && $user->belongsToTenant())
                    <flux:sidebar.group :heading="__('My work')" class="grid">
                        <flux:sidebar.item icon="calendar-days" :href="route('app.bookings')" :current="request()->routeIs('app.bookings')" wire:navigate>
                            {{ __('Bookings') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" :href="route('app.reports')" :current="request()->routeIs('app.reports')" wire:navigate>
                            {{ __('Reports') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clock" :href="route('app.availability')" :current="request()->routeIs('app.availability')" wire:navigate>
                            {{ __('Availability') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            @if ($user?->tenant)
                <flux:sidebar.nav>
                    <flux:sidebar.item icon="arrow-top-right-on-square" :href="$user->tenant->publicUrl()" target="_blank">
                        {{ __('View public page') }}
                    </flux:sidebar.item>
                </flux:sidebar.nav>
            @endif

            <x-locale-switcher class="px-2 pb-2" />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
