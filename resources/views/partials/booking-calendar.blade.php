@php
    $portalTimezone = $tenant->timezone;
    $today = \Carbon\CarbonImmutable::now($portalTimezone)->format('Y-m-d');
    $drawnMonth = \Carbon\CarbonImmutable::parse($month, $portalTimezone);
@endphp

<div class="space-y-4">
    <div class="flex items-center justify-between gap-2">
        <h2 class="font-semibold capitalize">{{ $drawnMonth->translatedFormat('F Y') }}</h2>

        <div class="flex items-center gap-1">
            <flux:button
                size="sm"
                variant="ghost"
                icon="chevron-left"
                wire:click="previousMonth"
                :disabled="! $this->canGoBack"
                :title="__('Previous month')"
            />
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="nextMonth" :title="__('Next month')" />
        </div>
    </div>

    <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-zinc-400 dark:text-zinc-500">
        @foreach (\App\Support\Week::shortLabels() as $label)
            <div>{{ $label }}</div>
        @endforeach
    </div>

    <div class="grid grid-cols-7 gap-1">
        @foreach ($this->calendar as $weekIndex => $week)
            @foreach ($week as $day)
                @php
                    $date = $day->format('Y-m-d');
                    $inMonth = $day->month === $drawnMonth->month;
                    $hasSlots = isset($this->monthSlots[$date]);
                    $isSelected = $selectedDate === $date;
                @endphp

                <div wire:key="day-{{ $weekIndex }}-{{ $date }}" class="aspect-square">
                    @if ($hasSlots)
                        <button
                            type="button"
                            wire:click="selectDate('{{ $date }}')"
                            @class([
                                'flex size-full flex-col items-center justify-center rounded-lg text-sm font-medium transition',
                                'text-white' => $isSelected,
                                'bg-zinc-100 text-zinc-900 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-700' => ! $isSelected,
                                'opacity-60' => ! $inMonth,
                            ])
                            @style(['background-color: var(--portal-brand)' => $isSelected])
                        >
                            {{ $day->day }}
                            <span @class([
                                'mt-0.5 size-1 rounded-full',
                                'bg-white' => $isSelected,
                                'bg-zinc-400 dark:bg-zinc-500' => ! $isSelected,
                            ])></span>
                        </button>
                    @else
                        <div @class([
                            'flex size-full items-center justify-center rounded-lg text-sm',
                            'text-zinc-300 dark:text-zinc-700' => ! $inMonth,
                            'text-zinc-400 dark:text-zinc-600' => $inMonth,
                            'ring-1 ring-inset ring-zinc-200 dark:ring-zinc-800' => $date === $today,
                        ])>
                            {{ $day->day }}
                        </div>
                    @endif
                </div>
            @endforeach
        @endforeach
    </div>

    @if ($this->monthSlots === [])
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('No availability this month. Try the next one.') }}
        </p>
    @endif
</div>
