<?php

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    #[Locked]
    public Tenant $tenant;

    /**
     * Resolve the portal and refuse suspended ones.
     */
    public function mount(Tenant $tenant): void
    {
        abort_unless($tenant->is_active, 404);

        $this->tenant = $tenant;

        if (! session()->has('locale') && ! request()->filled('lang')) {
            app()->setLocale($tenant->locale);
        }
    }

    /**
     * The services published by this portal.
     *
     * @return Collection<int, Service>
     */
    #[Computed]
    public function services(): Collection
    {
        return $this->tenant->services()
            ->active()
            ->with('operators')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->filter(fn (Service $service) => $service->operators->where('is_bookable', true)->where('is_active', true)->isNotEmpty())
            ->values();
    }
}; ?>

<x-slot:title>{{ $tenant->name }}</x-slot>

<div class="flex min-h-screen flex-col" style="--portal-brand: {{ $tenant->brand_color }}">
    <x-portal-header :tenant="$tenant" />

    <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:px-6 sm:py-12">
        <div class="space-y-10">
        <div class="flex flex-col items-start gap-5 sm:flex-row sm:items-center">
            <span
                class="flex size-16 shrink-0 items-center justify-center rounded-2xl text-xl font-semibold text-white shadow-sm"
                style="background-color: var(--portal-brand)"
            >{{ $tenant->initials() }}</span>

            <div class="min-w-0 space-y-2">
                <h1 class="text-3xl font-semibold tracking-tight">{{ $tenant->name }}</h1>

                @if ($tenant->description)
                    <p class="max-w-2xl text-zinc-600 dark:text-zinc-400">{{ $tenant->description }}</p>
                @endif

                <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                    @if ($tenant->address)
                        <span class="inline-flex items-center gap-1.5">
                            <flux:icon name="map-pin" variant="micro" /> {{ $tenant->address }}
                        </span>
                    @endif

                    @if ($tenant->contact_phone)
                        <span class="inline-flex items-center gap-1.5">
                            <flux:icon name="phone" variant="micro" /> {{ $tenant->contact_phone }}
                        </span>
                    @endif

                    @if ($tenant->website)
                        <a href="{{ $tenant->website }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 hover:underline">
                            <flux:icon name="globe-alt" variant="micro" /> {{ __('Website') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <h2 class="text-lg font-semibold">{{ __('Choose a service') }}</h2>

            @if ($this->services->isEmpty())
                <div class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
                    <p class="text-zinc-500 dark:text-zinc-400">{{ __('No service is bookable right now. Please check back later.') }}</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($this->services as $service)
                        <a
                            href="{{ route('portal.service', [$tenant, $service]) }}"
                            wire:navigate
                            class="group relative flex flex-col gap-3 overflow-hidden rounded-2xl border border-zinc-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900"
                            wire:key="service-{{ $service->id }}"
                        >
                            <span class="absolute inset-x-0 top-0 h-1" style="background-color: {{ $service->color }}"></span>

                            <div class="flex items-start justify-between gap-3 pt-1">
                                <h3 class="text-base font-semibold">{{ $service->name }}</h3>
                                <flux:icon name="arrow-right" variant="micro" class="mt-1 shrink-0 text-zinc-400 transition group-hover:translate-x-0.5" />
                            </div>

                            @if ($service->description)
                                <p class="line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $service->description }}</p>
                            @endif

                            <div class="mt-auto flex flex-wrap items-center gap-2 pt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                <span class="inline-flex items-center gap-1.5">
                                    <flux:icon name="clock" variant="micro" /> {{ $service->formattedDuration() }}
                                </span>

                                <span class="inline-flex items-center gap-1.5">
                                    <flux:icon :name="$service->location_type->icon()" variant="micro" /> {{ $service->location_type->label() }}
                                </span>

                                @if ($service->formattedPrice())
                                    <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $service->formattedPrice() }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
        </div>
    </main>

    <x-portal-footer :tenant="$tenant" />
</div>
