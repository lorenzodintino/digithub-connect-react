{{-- What can be done to a booking, from the table and from the calendar. --}}
<flux:dropdown position="bottom" align="end">
    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" inset="top bottom" />

    <flux:menu>
        @can('confirm', $booking)
            <flux:menu.item icon="check" wire:click="confirm('{{ $booking->uid }}')">
                {{ __('Confirm') }}
            </flux:menu.item>
        @endcan

        @can('recordOutcome', $booking)
            @foreach (\App\Enums\BookingStatus::outcomes() as $outcome)
                @continue ($booking->status === $outcome)

                <flux:menu.item
                    :icon="$outcome->outcomeIcon()"
                    wire:click="recordOutcome('{{ $booking->uid }}', '{{ $outcome->value }}')"
                >
                    {{ $outcome->outcomeLabel() }}
                </flux:menu.item>
            @endforeach

            <flux:menu.separator />
        @endcan

        <flux:menu.item
            icon="arrow-top-right-on-square"
            :href="route('portal.booking', [$this->tenant, $booking])"
            target="_blank"
        >
            {{ __('Open customer page') }}
        </flux:menu.item>

        @can('cancel', $booking)
            <flux:menu.separator />

            <flux:menu.item icon="x-mark" variant="danger" wire:click="askToCancel({{ $booking->id }})">
                {{ __('Cancel') }}
            </flux:menu.item>
        @endcan

        @can('anonymise', $booking)
            <flux:menu.item icon="user-minus" variant="danger" wire:click="askToAnonymise({{ $booking->id }})">
                {{ __('Erase customer data') }}
            </flux:menu.item>
        @endcan
    </flux:menu>
</flux:dropdown>
