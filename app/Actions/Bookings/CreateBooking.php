<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceSession;
use App\Models\User;
use App\Services\AgendaLock;
use App\Services\BookingNotifier;
use App\Services\GroupSlotFinder;
use App\Services\PackagePlan;
use App\Services\ResourceAvailability;
use App\Services\SlotFinder;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CreateBooking
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    /**
     * Book a slot for a customer.
     *
     * A null operator means the visitor did not care who they meet. The
     * candidates are then tried in turn, so losing a race to one of them moves
     * the booking to the next instead of failing outright.
     *
     * @param  array{name: string, email: string, phone?: string|null, notes?: string|null, timezone?: string|null, locale?: string|null, privacy_accepted?: bool}  $customer
     * @param  array<int, string|array<int, string>>  $answers  the extra answers, keyed by question id
     *
     * @throws SlotUnavailableException
     */
    public function handle(Service $service, ?User $operator, CarbonImmutable $startsAt, array $customer, array $answers = []): Booking
    {
        $startsAt = $startsAt->setTimezone('UTC');

        // A group service is not booked with a person, it is booked into a
        // class: the seat goes on the session, and the staff come with it.
        if ($service->isGroup()) {
            $booking = $this->bookSeat($service, $startsAt, $customer, $answers);

            $this->notifier->created($booking);

            return $booking;
        }

        $candidates = $operator === null
            ? $this->candidatesFor($service)
            : collect([$operator]);

        abort_if($candidates->isEmpty(), 404);

        $last = $candidates->count() - 1;

        foreach ($candidates->values() as $index => $candidate) {
            abort_unless($candidate->tenant_id === $service->tenant_id, 404);
            abort_unless($service->operators()->whereKey($candidate->getKey())->exists(), 404);

            try {
                $booking = $this->book($service, $candidate, $startsAt, $customer, $answers);
            } catch (SlotUnavailableException $exception) {
                // The last candidate has nobody left to hand over to.
                if ($index === $last) {
                    throw $exception;
                }

                continue;
            }

            // Sent outside the lock: nobody should wait on the mail queue while
            // the operator agenda is held.
            $this->notifier->created($booking);

            return $booking;
        }

        throw SlotUnavailableException::forSlot();
    }

    /**
     * Take a seat on a group session, opening the session when it is the first.
     *
     * The lock is on the session row rather than on an operator: what two
     * customers compete for here is the last seat, and that is a row that
     * already exists. Creating the very first seat is serialised by the unique
     * key on service and start instead, and a lost race simply re-reads the
     * session somebody else just opened.
     *
     * @param  array<string, mixed>  $customer
     * @param  array<int, string|array<int, string>>  $answers
     *
     * @throws SlotUnavailableException
     */
    private function bookSeat(Service $service, CarbonImmutable $startsAt, array $customer, array $answers): Booking
    {
        // Checked before the lock as well as inside it: the hour has to be one
        // the service is really offering, not merely one a session exists for.
        if (! (new GroupSlotFinder($service))->canBookAt($startsAt)) {
            throw SlotUnavailableException::forSlot();
        }

        return AgendaLock::forTenant($service->tenant_id, function () use ($service, $startsAt, $customer, $answers): Booking {
            $service->refresh()->load('tenant');
            if (! (new GroupSlotFinder($service))->canBookAt($startsAt)) {
                throw SlotUnavailableException::forSlot();
            }

            User::query()->whereIn('id', $service->operators()->pluck('users.id'))
                ->orderBy('id')->lockForUpdate()->get();
            $session = $this->sessionFor($service, $startsAt);

            if ($session->seatsTaken() >= $session->capacity) {
                throw SlotUnavailableException::forSlot();
            }

            // The primary operator is the one the emails and the agenda speak
            // of; the others are on the session and busy all the same.
            $primary = $session->operators()->orderBy('users.id')->first();

            if ($primary === null) {
                throw SlotUnavailableException::forSlot();
            }

            $confirmed = ! $service->requires_confirmation;

            $booking = Booking::create([
                'uid' => Booking::newUid(),
                'tenant_id' => $service->tenant_id,
                'service_id' => $service->getKey(),
                'service_session_id' => $session->getKey(),
                'user_id' => $primary->getKey(),
                'customer_id' => $this->resolveCustomer($service, $customer)->getKey(),
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'] ?? null,
                'customer_notes' => $customer['notes'] ?? null,
                'customer_timezone' => $customer['timezone'] ?? $service->tenant->timezone,
                'customer_locale' => $customer['locale'] ?? $service->tenant->locale,
                'privacy_accepted_at' => ($customer['privacy_accepted'] ?? false) ? now() : null,
                'privacy_accepted_url' => ($customer['privacy_accepted'] ?? false)
                    ? $service->tenant->privacyPolicyLink()
                    : null,
                'starts_at' => $session->starts_at,
                'ends_at' => $session->ends_at,
                'blocked_starts_at' => $session->blocked_starts_at,
                'blocked_ends_at' => $session->blocked_ends_at,
                'status' => $confirmed ? BookingStatus::Confirmed : BookingStatus::Pending,
                'confirmed_at' => $confirmed ? now() : null,
                'ics_sequence' => 0,
                'price_cents' => $service->price_cents,
                'currency' => $service->currency,
            ]);

            $this->recordAnswers($booking, $service, $answers);

            Activity::log('booking.created', $booking, [
                'service' => $service->name,
                'session' => $session->getKey(),
            ]);

            $booking->sharedResourceAllocations()->attach($session->resourceAllocations()->whereNull('released_at')->pluck('id'));

            return $booking;
        });
    }

    /**
     * The session running at that instant, opened now if it is the first seat.
     *
     * @throws SlotUnavailableException
     */
    private function sessionFor(Service $service, CarbonImmutable $startsAt): ServiceSession
    {
        $existing = $service->sessions()->where('starts_at', $startsAt)->lockForUpdate()->first();

        if ($existing !== null && $existing->seatsTaken() > 0) {
            return $existing;
        }

        $finder = new GroupSlotFinder($service);
        $free = $finder->operatorsFreeAt($startsAt);

        if ($free->isEmpty()) {
            throw SlotUnavailableException::forSlot();
        }

        [$blockedStart, $blockedEnd] = (new SlotFinder($service, $free->first()))->blockedWindowFor($startsAt);

        $attributes = [
            'tenant_id' => $service->tenant_id,
            'service_id' => $service->getKey(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($service->duration_minutes),
            'blocked_starts_at' => $blockedStart,
            'blocked_ends_at' => $blockedEnd,
            'capacity' => $service->capacity,
        ];

        if ($existing === null) {
            $session = ServiceSession::create($attributes);
        } else {
            $session = $existing;
            $session->resourceAllocations()->whereNull('released_at')->update(['released_at' => now()]);
            $session->update($attributes);
        }

        $session->operators()->sync($free->pluck('id')->all());
        (new ResourceAvailability($service))->allocate($session);

        return $session->load('operators');
    }

    /**
     * Write the booking while holding the operator's agenda.
     *
     * @param  array<string, mixed>  $customer
     * @param  array<int, string|array<int, string>>  $answers
     *
     * @throws SlotUnavailableException
     */
    private function book(Service $service, User $operator, CarbonImmutable $startsAt, array $customer, array $answers): Booking
    {
        return AgendaLock::forOperator($operator, function () use ($service, $operator, $startsAt, $customer, $answers): Booking {
            $service->refresh()->load('tenant');
            $operator->refresh();
            $service = $service->bookingConfiguration();
            abort_unless($operator->tenant_id === $service->tenant_id && $service->operators()->whereKey($operator->id)->exists(), 404);
            $finder = new SlotFinder($service, $operator);

            // Re-checked under the lock: the availability seen by the visitor
            // may be minutes old by the time the form is submitted.
            if (! $finder->canBookAt($startsAt)) {
                throw SlotUnavailableException::forSlot();
            }

            [$blockedStart, $blockedEnd] = $finder->blockedWindowFor($startsAt);

            $confirmed = ! $service->requires_confirmation;

            $record = $this->resolveCustomer($service, $customer);

            $booking = Booking::create([
                'uid' => Booking::newUid(),
                'tenant_id' => $service->tenant_id,
                'service_id' => $service->getKey(),
                'user_id' => $operator->getKey(),
                'customer_id' => $record->getKey(),
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'] ?? null,
                'customer_notes' => $customer['notes'] ?? null,
                'customer_timezone' => $customer['timezone'] ?? $service->tenant->timezone,
                'customer_locale' => $customer['locale'] ?? $service->tenant->locale,
                // The proof of consent, and which document it covered: a portal
                // can publish a new policy tomorrow without rewriting the past.
                'privacy_accepted_at' => ($customer['privacy_accepted'] ?? false) ? now() : null,
                'privacy_accepted_url' => ($customer['privacy_accepted'] ?? false)
                    ? $service->tenant->privacyPolicyLink()
                    : null,
                'starts_at' => $startsAt,
                'ends_at' => $finder->endsAtFor($startsAt),
                'blocked_starts_at' => $blockedStart,
                'blocked_ends_at' => $blockedEnd,
                'status' => $confirmed ? BookingStatus::Confirmed : BookingStatus::Pending,
                'confirmed_at' => $confirmed ? now() : null,
                'ics_sequence' => 0,
                'price_cents' => $service->price_cents,
                'currency' => $service->currency,
            ]);

            $this->recordAnswers($booking, $service, $answers);

            if ($service->is_package) {
                (new PackagePlan($service))->recordPhases($booking);
            }
            (new ResourceAvailability($service))->allocate($booking);

            Activity::log('booking.created', $booking, [
                'service' => $service->name,
                'operator' => $operator->name,
            ]);

            return $booking;
        });
    }

    /**
     * Write down the answers, with the questions worded as they are today.
     *
     * An answer left empty is not stored: a booking should carry what was said,
     * not a row saying nothing was.
     *
     * @param  array<int, string|array<int, string>>  $answers
     */
    private function recordAnswers(Booking $booking, Service $service, array $answers): void
    {
        foreach ($service->bookingQuestions() as $position => $question) {
            $value = $answers[$question->id] ?? null;

            $value = is_array($value)
                ? implode(', ', array_filter($value, fn (string $choice) => trim($choice) !== ''))
                : trim((string) $value);

            if ($value === '') {
                continue;
            }

            $booking->answers()->create([
                'service_question_id' => $question->id,
                'label' => $question->label,
                'value' => $value,
                'position' => $position,
            ]);
        }
    }

    /**
     * Find the customer behind this booking, or write them down for the first time.
     *
     * Matching is by email inside the portal, and the freshest booking wins on
     * name and phone: it is the most recent thing the customer said about
     * themselves. What the portal wrote in `notes` is never touched here.
     *
     * @param  array<string, mixed>  $customer
     */
    private function resolveCustomer(Service $service, array $customer): Customer
    {
        return Customer::updateOrCreate(
            [
                'tenant_id' => $service->tenant_id,
                'email' => Customer::normaliseEmail($customer['email']),
            ],
            [
                'name' => $customer['name'],
                'phone' => $customer['phone'] ?? null,
                'timezone' => $customer['timezone'] ?? $service->tenant->timezone,
                'locale' => $customer['locale'] ?? $service->tenant->locale,
            ],
        );
    }

    /**
     * The operators to try, least busy first.
     *
     * Ordering by upcoming workload spreads the bookings around instead of
     * always loading whoever happens to come first alphabetically.
     *
     * @return Collection<int, User>
     */
    private function candidatesFor(Service $service): Collection
    {
        return $service->operators()
            ->bookable()
            ->withCount(['bookings' => fn ($query) => $query->blocking()->upcoming()])
            ->orderBy('bookings_count')
            ->orderBy('users.id')
            ->get();
    }
}
