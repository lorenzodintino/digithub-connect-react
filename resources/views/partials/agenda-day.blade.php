{{-- A single day, in the order the appointments happen. --}}
@if ($this->dayBookings->isEmpty())
    <div class="flex flex-col items-center gap-3 px-6 py-16 text-center">
        <flux:icon name="calendar-days" class="size-10 text-zinc-400" />
        <flux:heading size="lg">{{ __('Nothing booked on this day') }}</flux:heading>
        <flux:text>{{ __('Pick another day, or widen the filters.') }}</flux:text>
    </div>
@else
    <div class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700">
        @foreach ($this->dayBookings as $booking)
            <div class="flex items-center gap-3 py-3" wire:key="day-entry-{{ $booking->id }}">
                <button
                    type="button"
                    wire:click="showBooking({{ $booking->id }})"
                    class="flex min-w-0 flex-1 items-center gap-3 rounded-lg px-2 py-1 text-start transition hover:bg-zinc-100 dark:hover:bg-zinc-800"
                >
                    <span class="shrink-0 text-sm font-medium tabular-nums">
                        {{ $booking->startsAtIn($timezone)->format('H:i') }} – {{ $booking->endsAtIn($timezone)->format('H:i') }}
                    </span>

                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $booking->service->displayColor() }}"></span>

                    <span class="min-w-0 flex-1">
                        <span @class([
                            'block truncate font-medium',
                            'text-zinc-400 line-through' => $booking->status === \App\Enums\BookingStatus::Cancelled,
                        ])>{{ $booking->displayCustomerName() }}</span>
                        <span class="block truncate text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $booking->service->name }}
                            @if ($this->seesWholePortal)
                                · {{ $booking->operator->name }}
                            @endif
                        </span>
                    </span>

                    @if ($booking->customer_notes)
                        <flux:icon name="chat-bubble-left-ellipsis" variant="micro" class="shrink-0 text-zinc-400" />
                    @endif
                </button>

                <flux:badge size="sm" :color="$booking->status->color()" class="shrink-0">
                    {{ $booking->status->label() }}
                </flux:badge>

                @include('partials.booking-actions', ['booking' => $booking])
            </div>
        @endforeach
    </div>
@endif
