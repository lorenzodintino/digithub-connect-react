{{-- One column per day, every booking of the week in full. --}}
@php
    $today = \Carbon\CarbonImmutable::now($this->tenant->timezone)->format('Y-m-d');
@endphp

<div class="grid gap-px overflow-hidden rounded-lg bg-zinc-200 sm:grid-cols-7 dark:bg-zinc-700">
    @foreach ($this->calendarDays as $day)
        @php
            $date = $day->format('Y-m-d');
            $dayBookings = $this->calendarBookings[$date] ?? collect();
        @endphp

        <div class="min-h-40 space-y-1 bg-white p-2 dark:bg-zinc-900" wire:key="week-{{ $date }}">
            <button
                type="button"
                wire:click="showDayView('{{ $date }}')"
                @class([
                    'mb-1 flex w-full items-baseline gap-1 rounded px-1 py-0.5 text-start transition hover:bg-zinc-100 dark:hover:bg-zinc-800',
                    'font-semibold' => $date === $today,
                ])
            >
                <span class="text-xs uppercase text-zinc-400 dark:text-zinc-500">{{ $day->translatedFormat('D') }}</span>
                <span @class([
                    'text-sm',
                    'text-zinc-900 dark:text-white' => $date === $today,
                    'text-zinc-500 dark:text-zinc-400' => $date !== $today,
                ])>{{ $day->day }}</span>
            </button>

            @forelse ($dayBookings as $booking)
                <button
                    type="button"
                    wire:key="week-entry-{{ $booking->id }}"
                    wire:click="showBooking({{ $booking->id }})"
                    class="flex w-full flex-col gap-0.5 rounded-md border-s-2 bg-zinc-50 px-2 py-1 text-start text-xs transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:hover:bg-zinc-800"
                    style="border-color: {{ $booking->service->displayColor() }}"
                >
                    <span class="tabular-nums text-zinc-500 dark:text-zinc-400">
                        {{ $booking->startsAtIn($timezone)->format('H:i') }}
                    </span>
                    <span @class([
                        'truncate font-medium',
                        'text-zinc-400 line-through' => $booking->status === \App\Enums\BookingStatus::Cancelled,
                    ])>{{ $booking->displayCustomerName() }}</span>
                </button>
            @empty
                <p class="px-1 text-xs text-zinc-300 dark:text-zinc-600">—</p>
            @endforelse
        </div>
    @endforeach
</div>
