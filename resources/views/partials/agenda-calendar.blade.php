{{-- Calendar shell: the heading, the arrows and the date picker shared by the
     month, week and day views. `$timezone` comes from the page. --}}
@php
    $days = $this->calendarDays;

    $heading = match ($view) {
        'week' => $days[0]->translatedFormat('d M').' – '.$days[6]->translatedFormat('d M Y'),
        'day' => $this->focusDay->translatedFormat('D d M Y'),
        default => $this->focusDay->translatedFormat('F Y'),
    };
@endphp

<div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="font-semibold capitalize">{{ $heading }}</h2>

        <div class="flex items-center gap-2">
            <flux:input
                type="date"
                size="sm"
                wire:model.live="focus"
                :label="__('Go to date')"
                class="max-w-44"
                label:class="sr-only"
            />

            <flux:button size="sm" variant="ghost" icon="chevron-left" wire:click="previous" :title="__('Previous')" />
            <flux:button size="sm" variant="subtle" wire:click="goToToday">{{ __('Today') }}</flux:button>
            <flux:button size="sm" variant="ghost" icon="chevron-right" wire:click="next" :title="__('Next')" />
        </div>
    </div>

    @if ($view === 'day')
        @include('partials.agenda-day')
    @elseif ($view === 'week')
        @include('partials.agenda-week')
    @else
        @include('partials.agenda-month')
    @endif
</div>
