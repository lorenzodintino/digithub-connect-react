@php
    $slotStart = \Carbon\CarbonImmutable::parse($selectedSlot, 'UTC')->setTimezone($visitorTimezone);
    $slotEnd = $slotStart->addMinutes($service->duration_minutes);
@endphp

<form wire:submit="book" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
        <div class="space-y-0.5">
            <div class="font-semibold capitalize">{{ $slotStart->translatedFormat('l d F Y') }}</div>
            <div class="text-sm tabular-nums text-zinc-600 dark:text-zinc-400">
                {{ $slotStart->format('H:i') }} – {{ $slotEnd->format('H:i') }}
                <span class="text-zinc-400 dark:text-zinc-500">({{ $visitorTimezone }})</span>
            </div>

            {{-- The same appointment on the portal's clock. Printed at the one
                 moment a wrong hour costs a missed appointment, and left to the
                 two times to speak for themselves: no sentence to translate,
                 and half-hour offsets need no arithmetic. --}}
            @if ($visitorTimezone !== $tenant->timezone)
                <div class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">
                    {{ $slotStart->setTimezone($tenant->timezone)->format('H:i') }} – {{ $slotEnd->setTimezone($tenant->timezone)->format('H:i') }}
                    ({{ $tenant->timezone }})
                </div>
            @endif
        </div>

        <flux:button size="sm" variant="subtle" icon="arrow-left" type="button" wire:click="clearSlot">
            {{ __('Change time') }}
        </flux:button>
    </div>

    @error('selectedSlot')
        <p class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ $message }}</p>
    @enderror

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:input wire:model="customerName" :label="__('Your name')" required autofocus autocomplete="name" />
        <flux:input wire:model="customerEmail" type="email" :label="__('Email')" required autocomplete="email" />
    </div>

    <flux:input wire:model="customerPhone" type="tel" :label="__('Phone')" autocomplete="tel" />

    <flux:textarea
        wire:model="customerNotes"
        :label="__('Anything we should know?')"
        rows="3"
        :placeholder="__('Optional')"
    />

    @if ($this->questions->isNotEmpty())
        <div class="space-y-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
            @include('partials.booking-questions')
        </div>
    @endif

    <div class="space-y-1">
        <label class="flex items-start gap-2 text-sm">
            <flux:checkbox wire:model="privacyAccepted" class="mt-0.5" />

            <span>
                {{ __('I accept how :name handles my data for this booking.', ['name' => $tenant->name]) }}

                @if ($tenant->privacyPolicyLink())
                    <a href="{{ $tenant->privacyPolicyLink() }}" target="_blank" rel="noopener" class="underline">
                        {{ __('Privacy policy') }}
                    </a>
                @endif

                @if ($tenant->termsLink())
                    <span class="text-zinc-400">&middot;</span>
                    <a href="{{ $tenant->termsLink() }}" target="_blank" rel="noopener" class="underline">
                        {{ __('Terms of service') }}
                    </a>
                @endif
            </span>
        </label>

        @error('privacyAccepted')
            <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <flux:button variant="primary" type="submit">
            {{ $service->requires_confirmation ? __('Request this slot') : __('Confirm booking') }}
        </flux:button>

        <span wire:loading wire:target="book" class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Saving…') }}
        </span>
    </div>

    @if ($service->requires_confirmation)
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Your request will be reviewed by :name before it is confirmed.', ['name' => $tenant->name]) }}
        </p>
    @endif
</form>
