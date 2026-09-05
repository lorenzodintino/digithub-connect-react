<?php

use App\Actions\Availability\BlockTime;
use App\Actions\Availability\CloseThePortal;
use App\Actions\Availability\SaveWeeklySchedule;
use App\Actions\Availability\SetDateOverride;
use App\Exceptions\PeriodHasBookingsException;
use App\Models\AvailabilityException;
use App\Models\Booking;
use App\Models\AvailabilityRule;
use App\Models\PortalClosure;
use App\Models\TimeBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use App\Support\Week;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Availability')] class extends Component {
    /**
     * The staff member whose availability is being edited.
     */
    public int $memberId;

    /**
     * The weekly schedule being edited, keyed by weekday.
     *
     * @var array<int, array{enabled: bool, ranges: array<int, array{starts_at: string, ends_at: string}>}>
     */
    public array $schedule = [];

    public string $exceptionDate = '';

    public bool $exceptionUnavailable = true;

    public string $exceptionStartsAt = '10:00';

    public string $exceptionEndsAt = '14:00';

    public string $exceptionReason = '';

    public string $blockDate = '';

    public string $blockStartsAt = '09:00';

    public string $blockEndsAt = '13:00';

    public string $blockReason = '';

    public string $closureStartsOn = '';

    public string $closureEndsOn = '';

    public string $closureReason = '';

    /**
     * Load the schedule of the authenticated user by default.
     */
    public function mount(): void
    {
        $this->memberId = auth()->id();

        $this->loadSchedule();
    }

    /**
     * The staff member currently selected.
     */
    #[Computed]
    public function member(): User
    {
        return User::findOrFail($this->memberId);
    }

    /**
     * The staff members the current user is allowed to edit.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function editableMembers(): Collection
    {
        $user = auth()->user();

        if (! $user->isManager()) {
            return collect([$user]);
        }

        return $user->tenant->users()->orderByRaw("role = 'manager' desc")->orderBy('name')->get();
    }

    /**
     * The upcoming date overrides of the selected member.
     *
     * @return Collection<int, AvailabilityException>
     */
    #[Computed]
    public function exceptions(): Collection
    {
        return $this->member->availabilityExceptions()->upcoming()->orderBy('date')->get();
    }

    /**
     * Upcoming bookings the current hours no longer cover.
     *
     * Shown as a standing warning: hours are a setting, so they can be changed
     * freely, but whatever ends up uncovered has to be visible.
     *
     * @return Collection<int, Booking>
     */
    #[Computed]
    public function uncoveredBookings(): Collection
    {
        return app(SaveWeeklySchedule::class)->uncoveredBookings($this->member);
    }

    /**
     * The upcoming ad hoc blocks of the selected member.
     *
     * @return Collection<int, TimeBlock>
     */
    #[Computed]
    public function timeBlocks(): Collection
    {
        return $this->member->timeBlocks()->upcoming()->orderBy('starts_at')->get();
    }

    /**
     * Reload the schedule whenever the selected member changes.
     */
    public function updatedMemberId(): void
    {
        $this->authorizeMember();

        unset($this->member, $this->exceptions, $this->timeBlocks, $this->uncoveredBookings);

        $this->loadSchedule();
    }

    /**
     * Build the editable schedule from the stored rules.
     */
    public function loadSchedule(): void
    {
        $this->authorizeMember();

        $rules = $this->member->availabilityRules()->orderBy('starts_at')->get()->groupBy('weekday');

        $this->schedule = collect(Week::ordered())
            ->mapWithKeys(function (int $weekday) use ($rules): array {
                $dayRules = $rules->get($weekday, collect());

                return [$weekday => [
                    'enabled' => $dayRules->isNotEmpty(),
                    'ranges' => $dayRules->isEmpty()
                        ? [['starts_at' => '09:00', 'ends_at' => '18:00']]
                        : $dayRules->map(fn (AvailabilityRule $rule) => [
                            'starts_at' => Week::time($rule->starts_at),
                            'ends_at' => Week::time($rule->ends_at),
                        ])->values()->all(),
                ]];
            })
            ->all();
    }

    /**
     * Append an empty time range to the given weekday.
     */
    public function addRange(int $weekday): void
    {
        $this->schedule[$weekday]['ranges'][] = ['starts_at' => '09:00', 'ends_at' => '13:00'];
        $this->schedule[$weekday]['enabled'] = true;
    }

    /**
     * Remove a time range from the given weekday.
     */
    public function removeRange(int $weekday, int $index): void
    {
        unset($this->schedule[$weekday]['ranges'][$index]);

        $this->schedule[$weekday]['ranges'] = array_values($this->schedule[$weekday]['ranges']);

        if ($this->schedule[$weekday]['ranges'] === []) {
            $this->schedule[$weekday]['enabled'] = false;
            $this->schedule[$weekday]['ranges'] = [['starts_at' => '09:00', 'ends_at' => '18:00']];
        }
    }

    /**
     * Copy the ranges of one weekday to every other enabled weekday.
     */
    public function copyToAllDays(int $weekday): void
    {
        $ranges = $this->schedule[$weekday]['ranges'];

        foreach (array_keys($this->schedule) as $day) {
            if ($day !== $weekday && $this->schedule[$day]['enabled']) {
                $this->schedule[$day]['ranges'] = $ranges;
            }
        }

        Flux::toast(variant: 'success', text: __('Hours copied to the other working days.'));
    }

    /**
     * Replace the stored weekly rules with the edited schedule.
     */
    public function saveSchedule(SaveWeeklySchedule $saveWeeklySchedule): void
    {
        $this->authorizeMember();

        // The range checks throw ValidationException by hand rather than going
        // through validate(), which is what normally clears the bag. Without
        // this, a corrected overlap keeps showing the old complaint.
        $this->resetValidation();

        $uncovered = $saveWeeklySchedule->handle($this->member, $this->validatedSchedule());

        unset($this->uncoveredBookings);

        if ($uncovered->isEmpty()) {
            Flux::toast(variant: 'success', text: __('Availability saved.'));

            return;
        }

        Flux::toast(
            variant: 'warning',
            text: trans_choice('Saved, but one booking now falls outside your hours.|Saved, but :count bookings now fall outside your hours.', $uncovered->count()),
        );
    }

    /**
     * The edited schedule, reduced to the ranges of the enabled days.
     *
     * @return array<int, array<int, array{starts_at: string, ends_at: string}>>
     */
    private function validatedSchedule(): array
    {
        $schedule = [];

        foreach ($this->schedule as $weekday => $day) {
            if (! $day['enabled']) {
                continue;
            }

            $sorted = collect($day['ranges'])->sortBy('starts_at')->values();
            $previousEnd = null;

            foreach ($sorted as $index => $range) {
                $start = AvailabilityRule::toMinutes($range['starts_at']);
                $end = AvailabilityRule::toMinutes($range['ends_at']);

                if ($end <= $start) {
                    throw ValidationException::withMessages([
                        "schedule.{$weekday}.ranges.{$index}.ends_at" => __('The end time must be after the start time on :day.', ['day' => Week::label($weekday)]),
                    ]);
                }

                if ($previousEnd !== null && $start < $previousEnd) {
                    throw ValidationException::withMessages([
                        "schedule.{$weekday}.ranges.{$index}.starts_at" => __('Time ranges overlap on :day.', ['day' => Week::label($weekday)]),
                    ]);
                }

                $previousEnd = $end;

                $schedule[$weekday][] = [
                    'starts_at' => $range['starts_at'],
                    'ends_at' => $range['ends_at'],
                ];
            }
        }

        return $schedule;
    }

    /**
     * Store a date specific override for the selected member.
     */
    public function addException(SetDateOverride $setDateOverride): void
    {
        $this->authorizeMember();

        $this->validate([
            'exceptionDate' => ['required', 'date', 'after_or_equal:today'],
            'exceptionUnavailable' => ['boolean'],
            'exceptionStartsAt' => ['required_if:exceptionUnavailable,false', 'nullable', 'date_format:H:i'],
            'exceptionEndsAt' => ['required_if:exceptionUnavailable,false', 'nullable', 'date_format:H:i', 'after:exceptionStartsAt'],
            'exceptionReason' => ['nullable', 'string', 'max:120'],
        ], attributes: [
            'exceptionDate' => __('date'),
            'exceptionStartsAt' => __('start time'),
            'exceptionEndsAt' => __('end time'),
            'exceptionReason' => __('reason'),
        ]);

        try {
            $setDateOverride->handle(
                $this->member,
                $this->exceptionDate,
                $this->exceptionUnavailable,
                $this->exceptionStartsAt,
                $this->exceptionEndsAt,
                $this->exceptionReason,
            );
        } catch (PeriodHasBookingsException $exception) {
            $this->addError('exceptionDate', $exception->getMessage());

            return;
        }

        $this->reset('exceptionDate', 'exceptionReason');

        unset($this->exceptions);

        Flux::modal('exception-form')->close();
        Flux::toast(variant: 'success', text: __('Date override saved.'));
    }

    /**
     * Remove a date specific override.
     */
    public function removeException(int $exceptionId): void
    {
        $this->authorizeMember();

        $this->member->availabilityExceptions()->whereKey($exceptionId)->delete();

        unset($this->exceptions);

        Flux::toast(variant: 'success', text: __('Date override removed.'));
    }

    /**
     * The closures of the whole portal that are still to come.
     *
     * @return Collection<int, PortalClosure>
     */
    #[Computed]
    public function closures(): Collection
    {
        $tenant = auth()->user()->tenant;

        if ($tenant === null) {
            return collect();
        }

        return PortalClosure::query()
            ->forTenant($tenant)
            ->upcoming($tenant->timezone)
            ->orderBy('starts_on')
            ->get();
    }

    /**
     * Shut the portal for a stretch of days, for everybody at once.
     */
    public function addClosure(CloseThePortal $closeThePortal): void
    {
        $tenant = auth()->user()->tenant;

        $this->authorize('update', $tenant);

        $this->validate([
            'closureStartsOn' => ['required', 'date'],
            'closureEndsOn' => ['required', 'date', 'after_or_equal:closureStartsOn'],
            'closureReason' => ['nullable', 'string', 'max:120'],
        ], attributes: [
            'closureStartsOn' => __('first day'),
            'closureEndsOn' => __('last day'),
            'closureReason' => __('reason'),
        ]);

        try {
            $closeThePortal->handle(
                $tenant,
                CarbonImmutable::parse($this->closureStartsOn, $tenant->timezone),
                CarbonImmutable::parse($this->closureEndsOn, $tenant->timezone),
                $this->closureReason,
            );
        } catch (PeriodHasBookingsException $exception) {
            $this->addError('closureStartsOn', $exception->getMessage());

            return;
        }

        $this->reset('closureStartsOn', 'closureEndsOn', 'closureReason');

        unset($this->closures, $this->timeBlocks);

        Flux::modal('closure-form')->close();
        Flux::toast(variant: 'success', text: __('Portal closure saved.'));
    }

    /**
     * Reopen the portal on a stretch of days that was closed.
     */
    public function removeClosure(int $closureId): void
    {
        $tenant = auth()->user()->tenant;

        $this->authorize('update', $tenant);

        PortalClosure::query()->forTenant($tenant)->whereKey($closureId)->delete();

        unset($this->closures);

        Flux::toast(variant: 'success', text: __('Portal closure removed.'));
    }

    /**
     * Store an ad hoc block, expressed in the portal timezone.
     */
    public function addTimeBlock(BlockTime $blockTime): void
    {
        $this->authorizeMember();

        $this->validate([
            'blockDate' => ['required', 'date', 'after_or_equal:today'],
            'blockStartsAt' => ['required', 'date_format:H:i'],
            'blockEndsAt' => ['required', 'date_format:H:i', 'after:blockStartsAt'],
            'blockReason' => ['nullable', 'string', 'max:120'],
        ], attributes: [
            'blockDate' => __('date'),
            'blockStartsAt' => __('start time'),
            'blockEndsAt' => __('end time'),
            'blockReason' => __('reason'),
        ]);

        $timezone = $this->member->tenant->timezone;

        try {
            $blockTime->handle(
                $this->member,
                CarbonImmutable::parse($this->blockDate.' '.$this->blockStartsAt, $timezone),
                CarbonImmutable::parse($this->blockDate.' '.$this->blockEndsAt, $timezone),
                $this->blockReason,
            );
        } catch (PeriodHasBookingsException $exception) {
            $this->addError('blockDate', $exception->getMessage());

            return;
        }

        $this->reset('blockDate', 'blockReason');

        unset($this->timeBlocks);

        Flux::modal('time-block-form')->close();
        Flux::toast(variant: 'success', text: __('Time block saved.'));
    }

    /**
     * Remove an ad hoc block.
     */
    public function removeTimeBlock(int $blockId): void
    {
        $this->authorizeMember();

        $this->member->timeBlocks()->whereKey($blockId)->delete();

        unset($this->timeBlocks);

        Flux::toast(variant: 'success', text: __('Time block removed.'));
    }

    /**
     * Abort unless the current user may edit the selected member.
     */
    protected function authorizeMember(): void
    {
        $user = auth()->user();

        if ($user->id === $this->memberId) {
            return;
        }

        abort_unless($user->isManager() && $user->canAccessTenant(User::findOrFail($this->memberId)->tenant_id), 403);
    }
}; ?>

<x-slot:title>{{ __('Availability') }}</x-slot>

<div class="w-full">
    <div class="flex w-full flex-col gap-6">
        <x-page-header :heading="__('Availability')" :subheading="__('Weekly working hours and one-off exceptions used to generate bookable slots.')">
            <x-slot:actions>
                @if ($this->editableMembers->count() > 1)
                    <flux:select wire:model.live="memberId" class="min-w-56">
                        @foreach ($this->editableMembers as $member)
                            <flux:select.option :value="$member->id">
                                {{ $member->name }}{{ $member->isManager() ? ' · '.__('Manager') : '' }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:button variant="primary" icon="check" wire:click="saveSchedule">
                    {{ __('Save') }}
                </flux:button>
            </x-slot:actions>
        </x-page-header>

        @if ($this->uncoveredBookings->isNotEmpty())
            @php($portalTimezone = $this->member->tenant->timezone)

            <flux:callout icon="exclamation-triangle" variant="warning">
                <flux:callout.heading>
                    {{ trans_choice('One booking falls outside these hours|:count bookings fall outside these hours', $this->uncoveredBookings->count()) }}
                </flux:callout.heading>

                <flux:callout.text>
                    {{ __('They are still confirmed. Move or cancel them, or widen the hours to cover them again.') }}

                    <ul class="mt-2 space-y-1">
                        @foreach ($this->uncoveredBookings->take(5) as $booking)
                            @php($start = $booking->starts_at->setTimezone($portalTimezone))

                            <li class="tabular-nums" wire:key="uncovered-{{ $booking->id }}">
                                <span class="capitalize">{{ $start->translatedFormat('D d M') }}</span>
                                {{ $start->format('H:i') }} · {{ $booking->displayCustomerName() }} · {{ $booking->service->name }}
                            </li>
                        @endforeach
                    </ul>
                </flux:callout.text>

                <x-slot name="actions">
                    <flux:button size="sm" :href="route('app.bookings')" wire:navigate>
                        {{ __('Open bookings') }}
                    </flux:button>
                </x-slot>
            </flux:callout>
        @endif

        @unless ($this->member->is_bookable)
            <flux:callout icon="exclamation-triangle" variant="warning">
                {{ __('This person is not bookable, so these hours will not appear on the public page.') }}
            </flux:callout>
        @endunless

        <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach (Week::ordered() as $weekday)
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start" wire:key="day-{{ $weekday }}">
                    <div class="w-40 shrink-0 pt-1">
                        <flux:switch
                            wire:model.live="schedule.{{ $weekday }}.enabled"
                            :label="Week::label($weekday)"
                            align="left"
                        />
                    </div>

                    <div class="flex-1">
                        @if ($schedule[$weekday]['enabled'] ?? false)
                            <div class="space-y-3">
                                @foreach ($schedule[$weekday]['ranges'] as $index => $range)
                                    <div class="flex flex-wrap items-start gap-2" wire:key="range-{{ $weekday }}-{{ $index }}">
                                        <flux:input
                                            wire:model="schedule.{{ $weekday }}.ranges.{{ $index }}.starts_at"
                                            type="time"
                                            class="max-w-36"
                                        />

                                        <span class="pt-2 text-zinc-400">–</span>

                                        <flux:input
                                            wire:model="schedule.{{ $weekday }}.ranges.{{ $index }}.ends_at"
                                            type="time"
                                            class="max-w-36"
                                        />

                                        <flux:button
                                            size="sm"
                                            variant="subtle"
                                            icon="trash"
                                            wire:click="removeRange({{ $weekday }}, {{ $index }})"
                                            :title="__('Remove')"
                                        />
                                    </div>

                                    @error("schedule.{$weekday}.ranges.{$index}.starts_at")
                                        <flux:text size="sm" class="!text-rose-600 dark:!text-rose-400">{{ $message }}</flux:text>
                                    @enderror

                                    @error("schedule.{$weekday}.ranges.{$index}.ends_at")
                                        <flux:text size="sm" class="!text-rose-600 dark:!text-rose-400">{{ $message }}</flux:text>
                                    @enderror
                                @endforeach

                                <div class="flex gap-2">
                                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="addRange({{ $weekday }})">
                                        {{ __('Add range') }}
                                    </flux:button>

                                    <flux:button size="sm" variant="ghost" icon="document-duplicate" wire:click="copyToAllDays({{ $weekday }})">
                                        {{ __('Copy to other days') }}
                                    </flux:button>
                                </div>
                            </div>
                        @else
                            <flux:text class="pt-2">{{ __('Closed') }}</flux:text>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if (auth()->user()->isManager())
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <div>
                        <flux:heading size="lg">{{ __('Portal closures') }}</flux:heading>
                        <flux:text size="sm">{{ __('Holidays and days off for the whole business: nobody takes bookings on these days.') }}</flux:text>
                    </div>

                    <flux:modal.trigger name="closure-form">
                        <flux:button size="sm" variant="filled" icon="plus">{{ __('Add') }}</flux:button>
                    </flux:modal.trigger>
                </div>

                @if ($this->closures->isEmpty())
                    <div class="px-5 py-10 text-center">
                        <flux:text>{{ __('No closure planned.') }}</flux:text>
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->closures as $closure)
                            <div class="flex items-center justify-between gap-4 px-5 py-3" wire:key="closure-{{ $closure->id }}">
                                <div class="flex items-center gap-3">
                                    <flux:badge size="sm" color="rose">
                                        {{ __(':count days', ['count' => $closure->days()]) }}
                                    </flux:badge>

                                    <div>
                                        <div class="font-medium capitalize tabular-nums">
                                            {{ $closure->starts_on->translatedFormat('D d M Y') }}
                                            @if (! $closure->starts_on->isSameDay($closure->ends_on))
                                                – {{ $closure->ends_on->translatedFormat('D d M Y') }}
                                            @endif
                                        </div>

                                        @if ($closure->reason)
                                            <flux:text size="sm">{{ $closure->reason }}</flux:text>
                                        @endif
                                    </div>
                                </div>

                                <flux:button size="sm" variant="subtle" icon="trash" wire:click="removeClosure({{ $closure->id }})" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <div>
                    <flux:heading size="lg">{{ __('Time blocks') }}</flux:heading>
                    <flux:text size="sm">{{ __('Short periods you are away, without touching the weekly hours.') }}</flux:text>
                </div>

                <flux:modal.trigger name="time-block-form">
                    <flux:button size="sm" variant="filled" icon="plus">{{ __('Add') }}</flux:button>
                </flux:modal.trigger>
            </div>

            @if ($this->timeBlocks->isEmpty())
                <div class="px-5 py-10 text-center">
                    <flux:text>{{ __('No upcoming time blocks.') }}</flux:text>
                </div>
            @else
                @php($portalTimezone = $this->member->tenant->timezone)

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->timeBlocks as $block)
                        @php($blockStart = $block->starts_at->setTimezone($portalTimezone))

                        <div class="flex items-center justify-between gap-4 px-5 py-3" wire:key="block-{{ $block->id }}">
                            <div class="flex items-center gap-3">
                                <flux:badge size="sm" color="zinc">
                                    {{ $blockStart->format('H:i') }} – {{ $block->ends_at->setTimezone($portalTimezone)->format('H:i') }}
                                </flux:badge>

                                <div>
                                    <div class="font-medium capitalize tabular-nums">{{ $blockStart->translatedFormat('D d M Y') }}</div>
                                    @if ($block->reason)
                                        <flux:text size="sm">{{ $block->reason }}</flux:text>
                                    @endif
                                </div>
                            </div>

                            <flux:button size="sm" variant="subtle" icon="trash" wire:click="removeTimeBlock({{ $block->id }})" />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <div>
                    <flux:heading size="lg">{{ __('Date overrides') }}</flux:heading>
                    <flux:text size="sm">{{ __('Holidays, closures or special hours for a single day.') }}</flux:text>
                </div>

                <flux:modal.trigger name="exception-form">
                    <flux:button size="sm" variant="filled" icon="plus">{{ __('Add') }}</flux:button>
                </flux:modal.trigger>
            </div>

            @if ($this->exceptions->isEmpty())
                <div class="px-5 py-10 text-center">
                    <flux:text>{{ __('No upcoming overrides.') }}</flux:text>
                </div>
            @else
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->exceptions as $exception)
                        <div class="flex items-center justify-between gap-4 px-5 py-3" wire:key="exception-{{ $exception->id }}">
                            <div class="flex items-center gap-3">
                                <flux:badge size="sm" :color="$exception->is_unavailable ? 'rose' : 'indigo'">
                                    {{ $exception->is_unavailable ? __('Closed') : Week::time($exception->starts_at).' – '.Week::time($exception->ends_at) }}
                                </flux:badge>

                                <div>
                                    <div class="font-medium tabular-nums">{{ $exception->date->translatedFormat('D d M Y') }}</div>
                                    @if ($exception->reason)
                                        <flux:text size="sm">{{ $exception->reason }}</flux:text>
                                    @endif
                                </div>
                            </div>

                            <flux:button size="sm" variant="subtle" icon="trash" wire:click="removeException({{ $exception->id }})" />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <flux:modal name="exception-form" class="w-full max-w-md">
        <form wire:submit="addException" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Date override') }}</flux:heading>
                <flux:subheading>{{ __('Overrides the weekly hours for that single day.') }}</flux:subheading>
            </div>

            <flux:input wire:model="exceptionDate" type="date" :label="__('Date')" required />

            <flux:switch wire:model.live="exceptionUnavailable" :label="__('Closed all day')" />

            @unless ($exceptionUnavailable)
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="exceptionStartsAt" type="time" :label="__('From')" />
                    <flux:input wire:model="exceptionEndsAt" type="time" :label="__('To')" />
                </div>
            @endunless

            <flux:input wire:model="exceptionReason" :label="__('Reason')" :placeholder="__('Holidays, training, …')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">{{ __('Save override') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    <flux:modal name="closure-form" class="w-full max-w-md">
        <form wire:submit="addClosure" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Close the portal') }}</flux:heading>
                <flux:subheading>{{ __('Whole days, for every operator. Refused if anything is already booked inside.') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="closureStartsOn" type="date" :label="__('First day')" required />
                <flux:input wire:model="closureEndsOn" type="date" :label="__('Last day')" required />
            </div>

            <flux:input wire:model="closureReason" :label="__('Reason')" :placeholder="__('Holidays, public holiday, …')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">{{ __('Save closure') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="time-block-form" class="w-full max-w-md">
        <form wire:submit="addTimeBlock" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Time block') }}</flux:heading>
                <flux:subheading>{{ __('Removes the slots it covers, leaving the rest of the day open.') }}</flux:subheading>
            </div>

            <flux:input wire:model="blockDate" type="date" :label="__('Date')" required />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="blockStartsAt" type="time" :label="__('From')" required />
                <flux:input wire:model="blockEndsAt" type="time" :label="__('To')" required />
            </div>

            <flux:input wire:model="blockReason" :label="__('Reason')" :placeholder="__('Meeting, appointment, …')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit">{{ __('Save block') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
