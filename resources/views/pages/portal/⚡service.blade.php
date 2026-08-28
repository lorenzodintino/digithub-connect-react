<?php

use App\Actions\Bookings\CreateBooking;
use App\Exceptions\SlotUnavailableException;
use App\Models\Service;
use App\Models\ServiceQuestion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use App\Support\Timezones;
use App\Support\Week;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::portal')] class extends Component {
    #[Locked]
    public Tenant $tenant;

    #[Locked]
    public Service $service;

    public ?int $operatorId = null;

    /**
     * The first day of the month currently drawn, as Y-m-d in the portal timezone.
     */
    public string $month = '';

    #[Url(as: 'd', except: '')]
    public string $selectedDate = '';

    /**
     * The chosen slot as an ISO 8601 UTC string.
     */
    public string $selectedSlot = '';

    public string $visitorTimezone = '';

    public string $customerName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public string $customerNotes = '';

    public bool $privacyAccepted = false;

    /**
     * The answers to the extra questions, keyed by question id.
     *
     * @var array<int, string|array<int, string>>
     */
    public array $answers = [];

    /**
     * Resolve the portal and the requested service.
     */
    public function mount(Tenant $tenant, Service $service): void
    {
        abort_unless($tenant->is_active && $service->is_active && $service->tenant_id === $tenant->id, 404);

        // Multiple choice answers are lists from the start, so the checkboxes
        // have something to bind to.
        foreach ($service->questions as $question) {
            $this->answers[$question->id] = $question->type->isMultiple() ? [] : '';
        }

        $this->tenant = $tenant;
        $this->service = $service;
        $this->visitorTimezone = $tenant->timezone;
        // Null means "whoever is free", which is what most callers want when
        // a service has several people behind it.
        $this->operatorId = $this->operators->count() === 1
            ? $this->operators->first()->id
            : null;
        $this->month = CarbonImmutable::now($tenant->timezone)->startOfMonth()->format('Y-m-d');

        if (! session()->has('locale') && ! request()->filled('lang')) {
            app()->setLocale($tenant->locale);
        }
    }

    /**
     * The extra questions this service asks.
     *
     * @return Collection<int, ServiceQuestion>
     */
    #[Computed]
    public function questions(): Collection
    {
        return $this->service->questions()->get();
    }

    /**
     * The bookable operators delivering this service.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function operators(): Collection
    {
        return $this->service->operators()->bookable()->orderBy('name')->get();
    }

    /**
     * The operator the visitor picked, or null when they left it open.
     */
    #[Computed]
    public function operator(): ?User
    {
        return $this->operatorId === null
            ? null
            : $this->operators->firstWhere('id', $this->operatorId);
    }

    /**
     * Whether the visitor is happy with whoever is free.
     */
    #[Computed]
    public function anyOperator(): bool
    {
        return $this->operatorId === null && $this->operators->count() > 1;
    }

    /**
     * Slots of the drawn month, keyed by date in the portal timezone.
     *
     * With nobody chosen the slots of everyone are merged: a time is offered
     * as long as at least one person can take it.
     *
     * @return array<string, array<int, CarbonImmutable>>
     */
    #[Computed]
    public function monthSlots(): array
    {
        $people = $this->operator !== null
            ? collect([$this->operator])
            : $this->operators;

        if ($people->isEmpty()) {
            return [];
        }

        $month = CarbonImmutable::parse($this->month, $this->tenant->timezone);
        $merged = [];

        foreach ($people as $person) {
            $slots = (new SlotFinder($this->service, $person))
                ->slotsBetween($month->startOfMonth(), $month->endOfMonth());

            foreach ($slots as $date => $instants) {
                foreach ($instants as $instant) {
                    $merged[$date][$instant->getTimestamp()] = $instant;
                }
            }
        }

        foreach ($merged as $date => $instants) {
            ksort($instants);
            $merged[$date] = array_values($instants);
        }

        ksort($merged);

        return $merged;
    }

    /**
     * The calendar grid of the drawn month.
     *
     * @return array<int, array<int, CarbonImmutable>>
     */
    #[Computed]
    public function calendar(): array
    {
        return Week::monthMatrix(CarbonImmutable::parse($this->month, $this->tenant->timezone));
    }

    /**
     * The slots of the selected day.
     *
     * @return array<int, CarbonImmutable>
     */
    #[Computed]
    public function daySlots(): array
    {
        return $this->selectedDate === '' ? [] : ($this->monthSlots[$this->selectedDate] ?? []);
    }

    /**
     * Determine whether the visitor can still step back to an earlier month.
     */
    #[Computed]
    public function canGoBack(): bool
    {
        $month = CarbonImmutable::parse($this->month, $this->tenant->timezone);

        return $month->greaterThan(CarbonImmutable::now($this->tenant->timezone)->startOfMonth());
    }

    /**
     * Move the calendar one month back.
     */
    public function previousMonth(): void
    {
        if (! $this->canGoBack) {
            return;
        }

        $this->month = CarbonImmutable::parse($this->month, $this->tenant->timezone)->subMonth()->format('Y-m-d');

        $this->clearSelection();
    }

    /**
     * Move the calendar one month forward.
     */
    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month, $this->tenant->timezone)->addMonth()->format('Y-m-d');

        $this->clearSelection();
    }

    /**
     * Pick the operator to book with, or leave it open with null.
     */
    public function selectOperator(?int $operatorId = null): void
    {
        $this->operatorId = $operatorId;

        $this->clearSelection();

        unset($this->operator, $this->anyOperator, $this->monthSlots);
    }

    /**
     * Pick a day from the calendar.
     */
    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->selectedSlot = '';
    }

    /**
     * Pick a slot and reveal the booking form.
     */
    public function selectSlot(string $slot): void
    {
        $this->selectedSlot = $slot;
    }

    /**
     * Step back from the form to the slot list.
     */
    public function clearSlot(): void
    {
        $this->selectedSlot = '';
    }

    /**
     * Adopt the timezone detected in the browser, when it is one we offer.
     */
    public function useDetectedTimezone(string $timezone): void
    {
        if (Timezones::isSupported($timezone)) {
            $this->visitorTimezone = $timezone;
        }
    }

    /**
     * Place the booking and send the visitor to its confirmation page.
     */
    public function book(CreateBooking $createBooking): void
    {
        $rules = [
            'customerName' => ['required', 'string', 'max:120'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:40'],
            'customerNotes' => ['nullable', 'string', 'max:1000'],
            'privacyAccepted' => ['accepted'],
            'selectedSlot' => ['required', 'date'],
        ];

        $attributes = [
            'customerName' => __('name'),
            'customerEmail' => __('email'),
            'customerPhone' => __('phone'),
            'customerNotes' => __('notes'),
            'privacyAccepted' => __('privacy consent'),
            'selectedSlot' => __('time'),
        ];

        foreach ($this->questions as $question) {
            $key = 'answers.'.$question->id;
            $presence = $question->is_required ? 'required' : 'nullable';

            $rules[$key] = match (true) {
                $question->type->isMultiple() => [$presence, 'array'],
                $question->type->needsOptions() => [$presence, 'string', Rule::in($question->choices())],
                default => [$presence, 'string', 'max:'.$question->type->maxLength()],
            };

            if ($question->type->isMultiple()) {
                $rules[$key.'.*'] = ['string', Rule::in($question->choices())];
            }

            $attributes[$key] = mb_strtolower($question->label);
        }

        $validated = $this->validate($rules, attributes: $attributes);

        abort_if($this->operators->isEmpty(), 404);

        if (($wait = $this->bookingThrottleWait()) !== null) {
            $this->addError('selectedSlot', __('Too many booking attempts. Try again in :seconds seconds.', ['seconds' => $wait]));

            return;
        }

        try {
            $booking = $createBooking->handle(
                $this->service,
                // Null hands the choice to the action, which picks whoever is
                // free and least busy.
                $this->operator,
                CarbonImmutable::parse($validated['selectedSlot'], 'UTC'),
                [
                    'name' => $validated['customerName'],
                    'email' => $validated['customerEmail'],
                    'phone' => $validated['customerPhone'] ?: null,
                    'notes' => $validated['customerNotes'] ?: null,
                    'timezone' => $this->visitorTimezone,
                    'locale' => app()->getLocale(),
                    'privacy_accepted' => true,
                ],
                $this->answers,
            );
        } catch (SlotUnavailableException $exception) {
            $this->selectedSlot = '';

            unset($this->monthSlots, $this->daySlots);

            $this->addError('selectedSlot', $exception->getMessage());

            return;
        }

        $this->redirectRoute('portal.booking', [$this->tenant, $booking], navigate: true);
    }

    /**
     * Seconds to wait before this visitor may try to book again, or null.
     *
     * Nothing else guards this form: without a limit a script can fill an
     * agenda with junk appointments and fire the confirmation emails that go
     * with them.
     */
    private function bookingThrottleWait(): ?int
    {
        $key = 'booking:'.$this->service->id.':'.request()->ip();

        foreach ([[$key.':minute', 5, 60], [$key.':hour', 20, 3600]] as [$bucket, $attempts, $decay]) {
            if (RateLimiter::tooManyAttempts($bucket, $attempts)) {
                return RateLimiter::availableIn($bucket);
            }

            RateLimiter::hit($bucket, $decay);
        }

        return null;
    }

    /**
     * Forget the currently selected day and slot.
     */
    private function clearSelection(): void
    {
        $this->selectedDate = '';
        $this->selectedSlot = '';

        unset($this->monthSlots, $this->daySlots, $this->calendar);
    }
}; ?>

<x-slot:title>{{ $service->name.' · '.$tenant->name }}</x-slot>

<div
    class="flex min-h-screen flex-col"
    style="--portal-brand: {{ $tenant->brand_color }}; --portal-brand-contrast: {{ $tenant->brandForeground() }}; --color-accent: {{ $tenant->brand_color }}; --color-accent-content: {{ $tenant->brand_color }}; --color-accent-foreground: {{ $tenant->brandForeground() }}"
    x-data
    x-init="$wire.useDetectedTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)"
>
    <x-portal-header :tenant="$tenant" />

    <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:px-6 sm:py-12">
        <div class="space-y-6">
            <a href="{{ route('portal.show', $tenant) }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                <flux:icon name="arrow-left" variant="micro" /> {{ __('All services') }}
            </a>

            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="h-1.5 w-full" style="background-color: {{ $service->displayColor() }}"></div>

                <div class="grid lg:grid-cols-[300px_minmax(0,1fr)]">
                    {{-- Service summary --}}
                    <div class="space-y-5 border-b border-zinc-200 p-6 dark:border-zinc-800 lg:border-b-0 lg:border-e">
                        <div class="space-y-2">
                            <h1 class="text-xl font-semibold tracking-tight">{{ $service->name }}</h1>

                            @if ($service->description)
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $service->description }}</p>
                            @endif
                        </div>

                        <dl class="space-y-2 text-sm">
                            <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                                <flux:icon name="clock" variant="micro" /> {{ $service->formattedDuration() }}
                            </div>

                            <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                                <flux:icon :name="$service->location_type->icon()" variant="micro" /> {{ $service->location_type->label() }}
                            </div>

                            @if ($service->formattedPrice())
                                <div class="flex items-center gap-2 font-medium">
                                    <flux:icon name="banknotes" variant="micro" /> {{ $service->formattedPrice() }}
                                </div>
                            @endif

                            @if ($service->requires_confirmation)
                                <div class="flex items-center gap-2 text-amber-700 dark:text-amber-400">
                                    <flux:icon name="check-badge" variant="micro" /> {{ __('Needs approval') }}
                                </div>
                            @endif
                        </dl>

                        @if ($service->location_value)
                            <p class="rounded-lg bg-zinc-50 p-3 text-sm text-zinc-600 dark:bg-zinc-800/60 dark:text-zinc-300">
                                {{ $service->location_value }}
                            </p>
                        @endif

                        @if ($this->operators->count() > 1)
                            <div class="space-y-2">
                                <h2 class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ __('Choose who you want to meet') }}
                                </h2>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        wire:click="selectOperator"
                                        @class([
                                            'rounded-full border px-3 py-1.5 text-sm transition',
                                            'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $this->anyOperator,
                                            'border-zinc-200 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500' => ! $this->anyOperator,
                                        ])
                                    >
                                        {{ __('Anyone available') }}
                                    </button>

                                    @foreach ($this->operators as $person)
                                        <button
                                            type="button"
                                            wire:click="selectOperator({{ $person->id }})"
                                            wire:key="operator-{{ $person->id }}"
                                            @class([
                                                'flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition',
                                                'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $this->operator?->id === $person->id,
                                                'border-zinc-200 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500' => $this->operator?->id !== $person->id,
                                            ])
                                        >
                                            <flux:avatar size="xs" :name="$person->name" :initials="$person->initials()" />
                                            {{ $person->name }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($this->anyOperator)
                            <p class="border-t border-zinc-100 pt-4 text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                                {{ __('You will be assigned to whoever is free at the time you choose.') }}
                            </p>
                        @elseif ($this->operator)
                            <div class="space-y-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                                <div class="flex items-center gap-3">
                                    <flux:avatar size="sm" :name="$this->operator->name" :initials="$this->operator->initials()" />
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium">{{ $this->operator->name }}</div>
                                        @if ($this->operator->job_title)
                                            <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $this->operator->job_title }}</div>
                                        @endif
                                    </div>
                                </div>

                                @if ($this->operator->bio)
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $this->operator->bio }}</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Calendar, slots and form --}}
                    <div class="p-6">
                        @if ($this->operators->isEmpty())
                            <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-700">
                                <p class="text-zinc-500 dark:text-zinc-400">{{ __('This service has nobody available at the moment.') }}</p>
                            </div>
                        @elseif ($selectedSlot !== '')
                            @include('partials.booking-form')
                        @else
                            <div class="grid gap-6 sm:grid-cols-[minmax(0,1fr)_200px]">
                                @include('partials.booking-calendar')
                                @include('partials.booking-slots')
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </main>

    <x-portal-footer :tenant="$tenant" />
</div>
