<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Bookings\CreateBooking;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicBookingRequest;
use App\Models\Service;
use App\Models\ServiceQuestion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GroupSlotFinder;
use App\Services\SlotFinder;
use App\Support\CalendarNotes;
use App\Support\PortalPage;
use App\Support\SlotCalendar;
use App\Support\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The booking page of one service: the month, the free times of the chosen
 * day, the form, and the waiting list for when nothing suits.
 *
 * The query string holds the state, so a link can be shared and the back
 * button works: `month` (Y-m), `operator` (an id, or nothing for whoever is
 * free), `d` (the chosen day) and `tz` (the visitor's timezone).
 */
class ServicePageController extends Controller
{
    public function show(Request $request, Tenant $tenant, Service $service): Response
    {
        $service = $this->bookable($tenant, $service);

        PortalPage::speak($request, $tenant->locale);

        $operators = $this->operators($service);
        $operator = $this->chosen($this->filter($request, 'operator'), $operators);
        $timezone = SlotCalendar::timezoneOf($request, $tenant->timezone);
        $month = SlotCalendar::monthOf($request, $tenant);
        $calendar = new SlotCalendar($tenant, $month, $timezone);
        [$from, $to] = $calendar->range();

        $slots = $operators->isEmpty() ? [] : $this->slots($service, $operator !== null ? collect([$operator]) : $operators, $from, $to);
        $seats = $service->isGroup() ? (new GroupSlotFinder($service))->seatsLeftBetween($from, $to) : [];
        $seatsLeft = fn (CarbonImmutable $slot): int => $seats[$slot->getTimestamp()] ?? (int) $service->capacity;
        $date = $this->dateFilter($request, 'd');
        $today = CarbonImmutable::now($tenant->timezone);

        return Inertia::render('portal/service', [
            'portal' => PortalPage::layout($tenant, $request),
            'service' => $this->service($service, $request),
            'operators' => $operators->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'initials' => $person->initials(),
                'avatar' => $person->avatarUrl(),
                'job_title' => $person->job_title,
                'bio' => $person->bio,
            ])->all(),
            'filters' => [
                'month' => $month->format('Y-m'),
                'operator' => $operator !== null && $operators->count() > 1 ? (string) $operator->id : '',
                'd' => $date,
                'tz' => $timezone,
            ],
            'chosenOperator' => $operator?->id,
            'calendar' => $calendar->month(
                $slots,
                $service->isGroup() ? $this->nearlyFullDays($service, $slots, $seatsLeft) : [],
                CalendarNotes::forService($service, $from, $to),
            ),
            'day' => $date === '' ? null : $calendar->day(
                $date,
                $slots[$date] ?? [],
                (int) $service->duration_minutes,
                $service->isGroup() ? fn (CarbonImmutable $slot): array => $this->seatsLabel($service, $seatsLeft($slot)) : null,
            ),
            'questions' => $service->bookingQuestions()->map(fn (ServiceQuestion $question) => [
                'id' => $question->id,
                'label' => $question->label,
                'type' => $question->type->value,
                'required' => $question->is_required,
                'choices' => $question->choices(),
            ])->values()->all(),
            'timezones' => Timezones::options(),
            'portalTimezone' => $tenant->timezone,
            'waitingDefaults' => [
                'from' => $month->max($today->startOfDay())->format('Y-m-d'),
                'to' => $month->endOfMonth()->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Place the booking and send the visitor to its page.
     */
    public function book(PublicBookingRequest $request, Tenant $tenant, Service $service, CreateBooking $createBooking): RedirectResponse
    {
        $service = $this->bookable($tenant, $service);

        PortalPage::speak($request, $tenant->locale);

        $validated = $request->validated();
        $operators = $this->operators($service);

        abort_if($operators->isEmpty(), 404);

        if (($wait = PortalPage::throttle($service, $request)) !== null) {
            throw ValidationException::withMessages([
                'slot' => trans_choice('Too many booking attempts. Try again in one second.|Too many booking attempts. Try again in :count seconds.', $wait),
            ]);
        }

        $timezone = is_string($validated['tz'] ?? null) && Timezones::isSupported($validated['tz']) ? $validated['tz'] : $tenant->timezone;

        try {
            $booking = $createBooking->handle(
                $service,
                // Null hands the choice to the action, which picks whoever is
                // free and least busy.
                $this->chosen((string) ($validated['operator'] ?? ''), $operators),
                CarbonImmutable::parse($validated['slot'], 'UTC'),
                [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => ($validated['phone'] ?? null) ?: null,
                    'notes' => ($validated['notes'] ?? null) ?: null,
                    'timezone' => $timezone,
                    'locale' => app()->getLocale(),
                    'privacy_accepted' => true,
                ],
                $request->answers(),
            );
        } catch (SlotUnavailableException $exception) {
            throw ValidationException::withMessages(['slot' => $exception->getMessage()]);
        }

        return to_route('portal.booking', [$tenant, $booking, ...PortalPage::keepEmbed($request)]);
    }

    /**
     * The service as it is booked today, or 404 when the portal or the
     * service is not open to the public.
     */
    private function bookable(Tenant $tenant, Service $service): Service
    {
        $service = $service->bookingConfiguration();

        abort_unless($tenant->isOperational() && $service->is_active && $service->tenant_id === $tenant->id, 404);

        return $service;
    }

    /**
     * The bookable people delivering this service.
     *
     * @return Collection<int, User>
     */
    private function operators(Service $service): Collection
    {
        return $service->operators()->bookable()->orderBy('name')->get()
            ->filter(fn (User $operator) => $service->operatorCanDeliver($operator))
            ->values();
    }

    /**
     * The person the visitor picked; with a single person there is no choice
     * to make. Null means "whoever is free".
     *
     * @param  Collection<int, User>  $operators
     */
    private function chosen(string $operatorId, Collection $operators): ?User
    {
        if ($operators->count() === 1) {
            return $operators->first();
        }

        return ctype_digit($operatorId) ? $operators->firstWhere('id', (int) $operatorId) : null;
    }

    /**
     * Free times keyed by date in the portal timezone. With nobody chosen the
     * times of everyone are merged: a time is offered as long as one person
     * can take it.
     *
     * @param  Collection<int, User>  $people
     * @return array<string, array<int, CarbonImmutable>>
     */
    private function slots(Service $service, Collection $people, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($service->isGroup()) {
            return (new GroupSlotFinder($service))->slotsBetween($from, $to);
        }

        $merged = [];

        foreach ($people as $person) {
            foreach ((new SlotFinder($service, $person))->slotsBetween($from, $to) as $date => $instants) {
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
     * The days where every time offered is running out of seats.
     *
     * A day counts as nearly full only when **all** its times are: marking it
     * because a single hour is short would send the visitor into a day with
     * the room still half empty.
     *
     * @param  array<string, array<int, CarbonImmutable>>  $slots
     * @param  callable(CarbonImmutable): int  $seatsLeft
     * @return array<string, int>
     */
    private function nearlyFullDays(Service $service, array $slots, callable $seatsLeft): array
    {
        $days = [];

        foreach ($slots as $date => $instants) {
            if ($instants === []) {
                continue;
            }

            $seats = array_map($seatsLeft, $instants);
            $low = array_filter($seats, fn (int $left) => $service->seatsRunningLow($left));

            if (count($low) === count($seats)) {
                $days[$date] = min($seats);
            }
        }

        return $days;
    }

    /**
     * How a time of a class reads: seats free, or the last ones going.
     *
     * @return array{label: string, low: bool}
     */
    private function seatsLabel(Service $service, int $seatsLeft): array
    {
        $low = $service->seatsRunningLow($seatsLeft);

        return [
            'label' => $low
                ? trans_choice('last seat left|last :count seats', $seatsLeft)
                : trans_choice('one seat free|:count seats free', $seatsLeft),
            'low' => $low,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function service(Service $service, Request $request): array
    {
        return [
            'slug' => $service->slug,
            'name' => $service->name,
            'description' => $service->description,
            'color' => $service->displayColor(),
            'duration' => $service->formattedDuration(),
            'location' => [
                'type' => $service->location_type->value,
                'label' => $service->location_type->label(),
                'value' => $service->location_value,
            ],
            'price' => $service->formattedPrice(),
            'requires_confirmation' => (bool) $service->requires_confirmation,
            'is_group' => $service->isGroup(),
            'capacity' => (int) $service->capacity,
            'package' => $service->is_package ? $service->packageItems->map(fn ($item) => [
                'name' => $item->service->name,
                'duration' => $item->service->formattedDuration(),
                'pause' => $item->pause_after_minutes > 0 ? trans_choice('one minute pause|:count minutes pause', $item->pause_after_minutes) : null,
            ])->values()->all() : [],
            // Joining needs the feature in the plan and a place left: otherwise the form is not offered.
            'waiting_list_open' => $service->tenant->planAllows('waiting_list') && ! $service->tenant->planUsage()->waitingListFull($service),
            'urls' => [
                'book' => route('portal.service.book', [$service->tenant, $service, ...PortalPage::keepEmbed($request)]),
                'waiting_list' => route('portal.service.waiting-list', [$service->tenant, $service, ...PortalPage::keepEmbed($request)]),
            ],
        ];
    }
}
