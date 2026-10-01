<div class="space-y-3">
    <div class="space-y-2">
        <h2 class="font-semibold">
            @if ($selectedDate === '')
                {{ __('Pick a day') }}
            @else
                <span class="capitalize">{{ \Carbon\CarbonImmutable::parse($selectedDate, $tenant->timezone)->translatedFormat('D d M') }}</span>
            @endif
        </h2>

        {{-- Named, because an unlabelled "Europe/Rome" above a column of times
             reads as a setting nobody asked for. The same word every other
             timezone select in the app uses. --}}
        <flux:select wire:model.live="visitorTimezone" size="sm" :label="__('Timezone')">
            @foreach (\App\Support\Timezones::options() as $timezone)
                <flux:select.option :value="$timezone">{{ $timezone }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($visitorTimezone !== $tenant->timezone)
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('The portal works on :zone time.', ['zone' => $tenant->timezone]) }}
            </p>
        @endif
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
                    class="w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm font-medium tabular-nums transition hover:border-(--portal-brand) hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-(--portal-brand) dark:hover:bg-zinc-800"
                >
                    {{ $local->format('H:i') }}

                    @if ($local->format('Y-m-d') !== $selectedDate)
                        <span class="ms-1 text-xs font-normal text-zinc-500 dark:text-zinc-400">
                            {{ $local->translatedFormat('D d M') }}
                        </span>
                    @endif

                    @if ($service->isGroup())
                        @php($seatsLeft = $this->seatsLeftAt($slot))

                        <span @class([
                            'ms-1 text-xs font-normal',
                            'font-medium text-amber-700 dark:text-amber-400' => $service->seatsRunningLow($seatsLeft),
                            'text-zinc-500 dark:text-zinc-400' => ! $service->seatsRunningLow($seatsLeft),
                        ])>
                            @if ($service->seatsRunningLow($seatsLeft))
                                · {{ trans_choice('last seat left|last :count seats', $seatsLeft) }}
                            @else
                                · {{ trans_choice('one seat free|:count seats free', $seatsLeft) }}
                            @endif
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif
</div>
