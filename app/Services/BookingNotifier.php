<?php

namespace App\Services;

use App\Mail\BookingMail;
use App\Mail\Customer;
use App\Mail\Staff;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Decides who hears about a booking, and in which language.
 *
 * Customer and staff get separate messages rather than one with the other in
 * copy: the operator needs contact details and notes, the customer needs the
 * link to their booking, and neither should see the other's address.
 *
 * Every mailable is queued, so a slow or failing mail server never blocks the
 * booking itself.
 */
class BookingNotifier
{
    /**
     * A booking has just been placed by a customer.
     */
    public function created(Booking $booking): void
    {
        $customerMail = $booking->awaitsConfirmation()
            ? new Customer\BookingPendingMail($booking)
            : new Customer\BookingConfirmedMail($booking);

        $this->toCustomer($booking, $customerMail);
        $this->toOperator($booking, new Staff\BookingPlacedMail($booking));
        $this->toManager($booking);
    }

    /**
     * Copy the portal manager on a new booking, when they asked for it.
     *
     * Skipped when the manager is the assigned operator: they already got it.
     */
    private function toManager(Booking $booking): void
    {
        $tenant = $booking->tenant;

        if (! $tenant->notify_manager_on_booking) {
            return;
        }

        $manager = $tenant->manager()->first();

        if ($manager === null || $manager->getKey() === $booking->user_id) {
            return;
        }

        Mail::to($manager->email, $manager->name)
            ->locale($manager->locale)
            ->send(new Staff\BookingPlacedMail($booking));
    }

    /**
     * A request waiting for approval has been accepted.
     */
    public function confirmed(Booking $booking): void
    {
        $this->toCustomer($booking, new Customer\BookingConfirmedMail($booking));
    }

    /**
     * The appointment is close: remind the customer.
     */
    public function reminder(Booking $booking): void
    {
        $this->toCustomer($booking, new Customer\BookingReminderMail($booking));
    }

    /**
     * A booking was cancelled. Only the other side needs telling.
     */
    public function cancelled(Booking $booking, ?User $actor): void
    {
        if ($actor === null) {
            $this->toOperator($booking, new Staff\BookingCancelledMail($booking));

            return;
        }

        $this->toCustomer($booking, new Customer\BookingCancelledMail($booking));
    }

    /**
     * A booking moved. Again, only the other side needs telling.
     */
    public function rescheduled(Booking $booking, ?User $actor): void
    {
        if ($actor === null) {
            $this->toOperator($booking, new Staff\BookingRescheduledMail($booking));

            return;
        }

        $this->toCustomer($booking, new Customer\BookingRescheduledMail($booking));
    }

    /**
     * Send to the customer, in the language they booked in.
     */
    private function toCustomer(Booking $booking, BookingMail $mailable): void
    {
        Mail::to($booking->customer_email, $booking->customer_name)
            ->locale($booking->customer_locale)
            ->send($mailable);
    }

    /**
     * Send to the assigned operator, in their own account language.
     */
    private function toOperator(Booking $booking, BookingMail $mailable): void
    {
        $operator = $booking->operator;

        Mail::to($operator->email, $operator->name)
            ->locale($operator->locale)
            ->send($mailable);
    }
}
