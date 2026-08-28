<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\AgendaLock;
use App\Services\BookingNotifier;
use App\Services\SlotFinder;
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
        foreach ($service->questions as $position => $question) {
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
