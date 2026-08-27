<div class="space-y-3">
    <div class="space-y-2">
        <h2 class="font-semibold">
            @if ($selectedDate === '')
                {{ __('Pick a day') }}
            @else
                <span class="capitalize">{{ \Carbon\CarbonImmutable::parse($selectedDate, $tenant->timezone)->translatedFormat('D d M') }}</span>
            @endif
        </h2>

        <flux:select wire:model.live="visitorTimezone" size="sm">
            @foreach (\App\Support\Timezones::options() as $timezone)
                <flux:select.option :value="$timezone">{{ $timezone }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @error('selectedSlot')
        <p class="rounded-lg bg-rose-50 p-2 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ $message }}</p>
    @enderror

    @if ($selectedDate === '')
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Days with a dot have free slots.') }}
        </p>
    @elseif ($this->daySlots === [])
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Nothing free on this day any more.') }}
        </p>
    @else
        <div class="grid max-h-96 gap-2 overflow-y-auto pe-1">
            @foreach ($this->daySlots as $slot)
                @php($local = $slot->setTimezone($visitorTimezone))

                <button
                    type="button"
                    wire:key="slot-{{ $slot->format('Uv') }}"
                    wire:click="selectSlot('{{ $slot->toIso8601String() }}')"
                    class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm font-medium tabular-nums transition hover:border-zinc-900 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-white dark:hover:bg-zinc-800"
                >
                    {{ $local->format('H:i') }}

                    @if ($local->format('Y-m-d') !== $selectedDate)
                        <span class="ms-1 text-xs font-normal text-zinc-500 dark:text-zinc-400">
                            {{ $local->translatedFormat('D d M') }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif
</div>
