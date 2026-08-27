<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Bookings made simple')])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <header class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-6">
            <x-app-logo class="!gap-2" />

            <div class="flex items-center gap-2">
                <x-locale-switcher align="end" />

                @auth
                    <flux:button size="sm" variant="primary" :href="route('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:button>
                @else
                    <flux:button size="sm" variant="primary" :href="route('login')" wire:navigate>
                        {{ __('Log in') }}
                    </flux:button>
                @endauth
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-6xl px-6 py-16 sm:py-24">
                <div class="max-w-3xl space-y-6">
                    <span class="inline-flex items-center gap-2 rounded-full border border-zinc-200 px-3 py-1 text-sm text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        {{ __('Booking platform for businesses') }}
                    </span>

                    <h1 class="text-4xl font-semibold tracking-tight sm:text-6xl">
                        {{ __('Bookings made simple') }}
                    </h1>

                    <p class="max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">
                        {{ __('Give every business its own booking portal: services, staff, opening hours and a public page ready to share by link or embed in any website.') }}
                    </p>

                    <div class="flex flex-wrap gap-3 pt-2">
                        <flux:button variant="primary" icon-trailing="arrow-right" :href="route('login')" wire:navigate>
                            {{ __('Access your portal') }}
                        </flux:button>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-6 pb-24">
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        ['icon' => 'squares-2x2', 'title' => __('Your services'), 'text' => __('Duration, buffers, price and booking rules for everything you offer.')],
                        ['icon' => 'user-group', 'title' => __('Your team'), 'text' => __('Each operator has their own login, profile and weekly availability.')],
                        ['icon' => 'globe-alt', 'title' => __('Your public page'), 'text' => __('One link to share, or an iframe to embed in your own website.')],
                    ] as $feature)
                        <div class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-900">
                                <flux:icon :name="$feature['icon']" variant="mini" />
                            </span>

                            <h2 class="mt-4 font-semibold">{{ $feature['title'] }}</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $feature['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </main>

        <footer class="mx-auto max-w-6xl px-6 pb-10">
            <div class="border-t border-zinc-200 pt-6 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                &copy; {{ now()->year }} {{ config('app.name') }}
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
