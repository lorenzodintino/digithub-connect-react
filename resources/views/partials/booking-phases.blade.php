@if ($phaseBooking->phases->isNotEmpty())
    <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:heading>{{ __('Package programme') }}</flux:heading>
        @foreach ($phaseBooking->phases as $phase)
            <div class="flex justify-between gap-4 text-sm">
                <span>{{ $phase->service_name }}</span>
                <span class="tabular-nums">{{ $phase->starts_at->setTimezone($phaseTimezone)->format('H:i') }} – {{ $phase->ends_at->setTimezone($phaseTimezone)->format('H:i') }}</span>
            </div>
            @if ($phase->pause_after_minutes > 0)
                <flux:text size="sm">{{ trans_choice('one minute pause|:count minutes pause', $phase->pause_after_minutes) }}</flux:text>
            @endif
        @endforeach
    </div>
@endif
