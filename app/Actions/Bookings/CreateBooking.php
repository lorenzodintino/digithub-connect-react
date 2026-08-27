<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Services\AgendaLock;
use App\Services\BookingNotifier;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;

class CreateBooking
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    /**
     * Book a slot for a customer.
     *
     * @param  array{name: string, email: string, phone?: string|null, notes?: string|null, timezone?: string|null, locale?: string|null}  $customer
     *
     * @throws SlotUnavailableException
     */
    public function handle(Service $service, User $operator, CarbonImmutable $startsAt, array $customer): Booking
    {
        $startsAt = $startsAt->setTimezone('UTC');

        abort_unless($operator->tenant_id === $service->tenant_id, 404);
        abort_unless($service->operators()->whereKey($operator->getKey())->exists(), 404);

        $booking = AgendaLock::forOperator($operator, function () use ($service, $operator, $startsAt, $customer): Booking {
            $finder = new SlotFinder($service, $operator);

            // Re-checked under the lock: the availability seen by the visitor
            // may be minutes old by the time the form is submitted.
            if (! $finder->canBookAt($startsAt)) {
                throw SlotUnavailableException::forSlot();
            }

            [$blockedStart, $blockedEnd] = $finder->blockedWindowFor($startsAt);

            $confirmed = ! $service->requires_confirmation;

            return Booking::create([
                'uid' => Booking::newUid(),
                'tenant_id' => $service->tenant_id,
                'service_id' => $service->getKey(),
                'user_id' => $operator->getKey(),
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'] ?? null,
                'customer_notes' => $customer['notes'] ?? null,
                'customer_timezone' => $customer['timezone'] ?? $service->tenant->timezone,
                'customer_locale' => $customer['locale'] ?? $service->tenant->locale,
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
        });

        // Sent outside the lock: nobody should wait on the mail queue while the
        // operator agenda is held.
        $this->notifier->created($booking);

        return $booking;
    }
}
