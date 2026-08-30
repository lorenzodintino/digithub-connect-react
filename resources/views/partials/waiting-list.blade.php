{{-- Nothing that suits? Leave a period and we write when it opens up. --}}
<div class="mt-6 border-t border-zinc-100 pt-4 dark:border-zinc-800">
    @if (! $waiting)
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:text size="sm">{{ __('Nothing that suits you?') }}</flux:text>

            <flux:button size="sm" variant="subtle" icon="bell-alert" wire:click="startWaiting">
                {{ __('Tell me when a place opens up') }}
            </flux:button>
        </div>
    @else
        <form wire:submit="joinWaitingList" class="space-y-4">
            <div>
                <flux:heading size="sm">{{ __('Waiting list') }}</flux:heading>
                <flux:subheading>
                    {{ __('We write to everybody waiting as soon as a place frees up, and the first to book takes it.') }}
                </flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="waitingName" :label="__('Your name')" required />
                <flux:input wire:model="waitingEmail" type="email" :label="__('Email')" required />
                <flux:input wire:model="waitingPhone" type="tel" :label="__('Phone')" />
                <div class="grid grid-cols-2 gap-2">
                    <flux:input wire:model="waitingFrom" type="date" :label="__('From')" required />
                    <flux:input wire:model="waitingTo" type="date" :label="__('To')" required />
                </div>
            </div>

            <div class="space-y-1">
                <label class="flex items-start gap-2 text-sm">
                    <flux:checkbox wire:model="waitingPrivacyAccepted" class="mt-0.5" />

                    <span>
                        {{ __('I accept how :name handles my data for this booking.', ['name' => $tenant->name]) }}

                        @if ($tenant->privacyPolicyLink())
                            <a href="{{ $tenant->privacyPolicyLink() }}" target="_blank" rel="noopener" class="underline">
                                {{ __('Privacy policy') }}
                            </a>
                        @endif
                    </span>
                </label>

                @error('waitingPrivacyAccepted')
                    <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button size="sm" variant="primary" type="submit">{{ __('Put me on the list') }}</flux:button>
                <flux:button size="sm" variant="subtle" type="button" wire:click="stopWaiting">{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    @endif
</div>
