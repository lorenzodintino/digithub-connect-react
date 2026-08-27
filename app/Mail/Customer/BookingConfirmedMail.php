<?php

namespace App\Mail\Customer;

use App\Mail\BookingMail;
use Illuminate\Mail\Mailables\Content;

/**
 * Tells the customer their booking is set.
 */
class BookingConfirmedMail extends BookingMail
{
    /**
     * The subject line of the message.
     */
    protected function subjectLine(): string
    {
        return __('Booking confirmed: :service', ['service' => $this->booking->service->name]);
    }

    /**
     * This message changes what the customer has in their calendar.
     */
    protected function carriesCalendarFile(): bool
    {
        return true;
    }

    /**
     * Build the message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.customer.booking-confirmed',
            with: [
                'booking' => $this->booking,
                'tenant' => $this->booking->tenant,
                'timezone' => $this->booking->customer_timezone,
                'manageUrl' => $this->manageUrl(),
            ],
        );
    }
}
