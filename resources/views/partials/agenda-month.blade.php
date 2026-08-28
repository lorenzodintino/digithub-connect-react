{{-- Month grid. Clicking the day number opens that day on its own. --}}
@php
    $today = \Carbon\CarbonImmutable::now($this->tenant->timezone)->format('Y-m-d');
@endphp

<div class="grid grid-cols-7 pb-2 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500">
    @foreach (\App\Support\Week::shortLabels() as $label)
        <div>{{ $label }}</div>
    @endforeach
</div>

<div class="grid grid-cols-7 gap-px overflow-hidden rounded-lg bg-zinc-200 dark:bg-zinc-700">
    @foreach ($this->calendarWeeks as $weekIndex => $week)
        @foreach ($week as $day)
            @php
                $date = $day->format('Y-m-d');
                $dayBookings = $this->calendarBookings[$date] ?? collect();
                $inMonth = $day->month === $this->focusDay->month;
            @endphp

            <div
                wire:key="cell-{{ $weekIndex }}-{{ $date }}"
                @class([
                    'min-h-24 space-y-1 bg-white p-1.5 dark:bg-zinc-900',
                    'bg-zinc-50 dark:bg-zinc-900/40' => ! $inMonth,
                ])
            >
                <button
                    type="button"
                    wire:click="showDayView('{{ $date }}')"
                    :title="__('Open this day')"
                    @class([
                        'flex size-6 items-center justify-center rounded-full text-xs transition hover:bg-zinc-100 dark:hover:bg-zinc-800',
                        'bg-zinc-900 font-semibold text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200' => $date === $today,
                        'text-zinc-400 dark:text-zinc-600' => $date !== $today && ! $inMonth,
                        'text-zinc-500 dark:text-zinc-400' => $date !== $today && $inMonth,
                    ])
                >{{ $day->day }}</button>

                @foreach ($dayBookings->take(3) as $booking)
                    <button
                        type="button"
                        wire:key="entry-{{ $booking->id }}"
                        wire:click="showBooking({{ $booking->id }})"
                        class="flex w-full items-center gap-1 rounded px-1 py-0.5 text-start text-xs transition hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    >
                        <span class="size-1.5 shrink-0 rounded-full" style="background-color: {{ $booking->service->displayColor() }}"></span>
                        <span class="shrink-0 tabular-nums text-zinc-500 dark:text-zinc-400">
                            {{ $booking->startsAtIn($timezone)->format('H:i') }}
                        </span>
                        <span @class([
                            'truncate',
                            'text-zinc-400 line-through' => $booking->status === \App\Enums\BookingStatus::Cancelled,
                        ])>{{ $booking->displayCustomerName() }}</span>
                    </button>
                @endforeach

                @if ($dayBookings->count() > 3)
                    <button
                        type="button"
                        wire:click="showDayView('{{ $date }}')"
                        class="w-full rounded px-1 py-0.5 text-start text-xs font-medium text-zinc-500 transition hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                    >
                        {{ __(':count more', ['count' => $dayBookings->count() - 3]) }}
                    </button>
                @endif
            </div>
        @endforeach
    @endforeach
</div>

@if ($this->calendarBookings->isEmpty())
    <p class="pt-4 text-center text-sm text-zinc-500 dark:text-zinc-400">
        {{ __('Nothing booked this month with these filters.') }}
    </p>
@endif
