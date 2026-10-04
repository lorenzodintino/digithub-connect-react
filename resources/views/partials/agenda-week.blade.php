{{-- The week on a time grid, like a wall calendar: one column per day, each
     appointment as tall as it lasts and side by side where they overlap. On a
     phone seven columns do not fit, so the week falls back to a list per day. --}}
@php
    $now = \Carbon\CarbonImmutable::now($this->tenant->timezone);
    $today = $now->format('Y-m-d');
    $grid = $this->weekGrid;
    $firstMinute = $grid['startHour'] * 60;
    $gridHeight = ($grid['endHour'] - $grid['startHour']) * 60;
    $nowMinute = $now->hour * 60 + $now->minute;
@endphp

<div class="hidden md:block">
    <div class="grid" style="grid-template-columns: 3.25rem repeat(7, minmax(0, 1fr))">
        <div></div>

        @foreach ($this->calendarDays as $day)
            @php
                $date = $day->format('Y-m-d');
            @endphp

            <button
                type="button"
                wire:click="showDayView('{{ $date }}')"
                wire:key="week-head-{{ $date }}"
                title="{{ __('Open this day') }}"
                class="flex items-baseline justify-center gap-1 rounded py-1.5 transition hover:bg-zinc-100 dark:hover:bg-zinc-800"
            >
                <span class="text-xs uppercase text-zinc-400 dark:text-zinc-500">{{ $day->translatedFormat('D') }}</span>
                <span @class([
                    'flex size-6 items-center justify-center rounded-full text-sm',
                    'bg-zinc-900 font-semibold text-white dark:bg-white dark:text-zinc-900' => $date === $today,
                    'text-zinc-600 dark:text-zinc-300' => $date !== $today,
                ])>{{ $day->day }}</span>
            </button>
        @endforeach

        <div class="relative" style="height: {{ $gridHeight }}px">
            @foreach (range($grid['startHour'], $grid['endHour'] - 1) as $hour)
                <span class="absolute end-2 -translate-y-1/2 text-xs tabular-nums text-zinc-400 dark:text-zinc-500" style="top: {{ ($hour - $grid['startHour']) * 60 }}px">
                    {{ sprintf('%02d:00', $hour) }}
                </span>
            @endforeach
        </div>

        @foreach ($this->calendarDays as $day)
            @php
                $date = $day->format('Y-m-d');
            @endphp

            <div
                wire:key="week-column-{{ $date }}"
                @class([
                    'relative border-s border-zinc-200 dark:border-zinc-700',
                    'bg-zinc-50/60 dark:bg-zinc-800/30' => $date === $today,
                ])
                style="height: {{ $gridHeight }}px; background-image: linear-gradient(to bottom, rgb(161 161 170 / .25) 1px, transparent 1px); background-size: 100% 60px"
            >
                @foreach ($grid['days'][$date] ?? [] as $entry)
                    @php
                        $booking = $entry['booking'];
                        $color = $booking->service->displayColor();
                        $cancelled = $booking->status === \App\Enums\BookingStatus::Cancelled;
                        $isSession = $booking->service_session_id !== null;
                        $label = $isSession
                            ? trans_choice('one participant|:count participants', $entry['seats'])
                            : $booking->displayCustomerName();
                    @endphp

                    <button
                        type="button"
                        wire:key="week-entry-{{ $booking->id }}"
                        @if ($isSession)
                            wire:click="showDayView('{{ $date }}')"
                        @else
                            wire:click="showBooking({{ $booking->id }})"
                        @endif
                        title="{{ $booking->startsAtIn($timezone)->format('H:i') }}–{{ $booking->endsAtIn($timezone)->format('H:i') }} · {{ $label }} · {{ $booking->service->name }}"
                        @class([
                            'absolute overflow-hidden rounded-md border-s-4 px-1.5 py-0.5 text-start text-xs leading-tight transition [--week-base:#fff] hover:z-10 hover:shadow-md dark:[--week-base:#18181b]',
                            'opacity-50' => $cancelled,
                        ])
                        style="top: {{ $entry['start'] - $firstMinute }}px; height: {{ max(18, $entry['end'] - $entry['start']) }}px; left: calc({{ $entry['lane'] }} * 100% / {{ $entry['lanes'] }} + 1px); width: calc(100% / {{ $entry['lanes'] }} - 2px); border-color: {{ $color }}; background-color: color-mix(in srgb, {{ $color }} 22%, var(--week-base))"
                    >
                        <span class="block tabular-nums text-zinc-600 dark:text-zinc-300">{{ $booking->startsAtIn($timezone)->format('H:i') }}</span>
                        <span @class([
                            'block truncate font-medium text-zinc-900 dark:text-white',
                            'line-through' => $cancelled,
                        ])>{{ $isSession ? $booking->service->name : $label }}</span>
                        @if ($isSession)
                            <span class="block truncate text-zinc-600 dark:text-zinc-300">{{ $label }}</span>
                        @elseif ($entry['end'] - $entry['start'] >= 45)
                            <span class="block truncate text-zinc-600 dark:text-zinc-300">{{ $booking->service->name }}</span>
                        @endif
                    </button>
                @endforeach

                @if ($date === $today && $nowMinute >= $firstMinute && $nowMinute <= $firstMinute + $gridHeight)
                    <div class="pointer-events-none absolute inset-x-0 z-20 h-0.5 bg-red-500" style="top: {{ $nowMinute - $firstMinute }}px" aria-hidden="true"></div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($this->calendarBookings->isEmpty())
        <p class="pt-4 text-center text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Nothing booked this week with these filters.') }}
        </p>
    @endif
</div>

<div class="grid gap-px overflow-hidden rounded-lg bg-zinc-200 md:hidden dark:bg-zinc-700">
    @foreach ($this->calendarDays as $day)
        @php
            $date = $day->format('Y-m-d');
            $dayBookings = $this->calendarBookings[$date] ?? collect();
        @endphp

        <div class="space-y-1 bg-white p-2 dark:bg-zinc-900" wire:key="week-{{ $date }}">
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
                    wire:key="week-list-entry-{{ $booking->id }}"
                    wire:click="showBooking({{ $booking->id }})"
                    class="flex w-full items-center gap-2 rounded-md border-s-2 bg-zinc-50 px-2 py-1 text-start text-xs transition hover:bg-zinc-100 dark:bg-zinc-800/60 dark:hover:bg-zinc-800"
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
