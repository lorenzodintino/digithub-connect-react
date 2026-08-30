<?php

use App\Models\Tenant;
use App\Models\WaitingListEntry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    #[Locked]
    public Tenant $tenant;

    #[Locked]
    public WaitingListEntry $entry;

    #[Locked]
    public bool $left = false;

    /**
     * Show the way out, without taking it yet.
     */
    public function mount(Tenant $tenant, WaitingListEntry $entry): void
    {
        abort_unless($entry->tenant_id === $tenant->id, 404);

        $this->tenant = $tenant;
        $this->entry = $entry;

        if (! session()->has('locale') && ! request()->filled('lang')) {
            app()->setLocale($entry->customer_locale);
        }
    }

    /**
     * Take the person off the list.
     *
     * Behind a button rather than on arrival: mail scanners and link previews
     * fetch every address in a message, and somebody who never clicked would
     * silently disappear from the list they had asked to join.
     */
    public function leave(): void
    {
        $this->entry->delete();

        $this->left = true;
    }
}; ?>

<x-slot:title>{{ __('Waiting list').' · '.$tenant->name }}</x-slot>

<div class="flex min-h-screen flex-col" style="--portal-brand: {{ $tenant->brand_color }}; --portal-brand-contrast: {{ $tenant->brandForeground() }}; --color-accent: {{ $tenant->brand_color }}; --color-accent-content: {{ $tenant->brand_color }}; --color-accent-foreground: {{ $tenant->brandForeground() }}">
    <x-portal-header :tenant="$tenant" />

    <main class="mx-auto w-full max-w-xl flex-1 px-4 py-16 sm:px-6">
        <div class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-8 text-center dark:border-zinc-800 dark:bg-zinc-900">
            @if ($left)
                <flux:icon name="check-circle" class="mx-auto size-10 text-emerald-500" />

                <flux:heading size="lg">{{ __('You are off the waiting list') }}</flux:heading>

                <flux:text>
                    {{ __('We will not write to you again about :service.', ['service' => $entry->service->name]) }}
                </flux:text>

                <flux:button variant="primary" :href="route('portal.show', $tenant)" wire:navigate>
                    {{ __('Back to the booking page') }}
                </flux:button>
            @else
                <flux:icon name="bell-slash" class="mx-auto size-10 text-zinc-400" />

                <flux:heading size="lg">{{ __('Leave this waiting list?') }}</flux:heading>

                <flux:text>
                    {{ __('You will stop hearing about places that open up for :service.', ['service' => $entry->service->name]) }}
                </flux:text>

                <div class="flex flex-wrap justify-center gap-2">
                    <flux:button variant="primary" wire:click="leave">
                        {{ __('Take me off the list') }}
                    </flux:button>

                    <flux:button variant="subtle" :href="route('portal.show', $tenant)" wire:navigate>
                        {{ __('Keep me on it') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </main>

    <x-portal-footer :tenant="$tenant" />
</div>
