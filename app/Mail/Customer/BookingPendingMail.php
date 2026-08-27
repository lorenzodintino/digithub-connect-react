<?php

namespace App\Mail\Customer;

use App\Mail\BookingMail;
use Illuminate\Mail\Mailables\Content;

/**
 * Acknowledges a request that still needs the portal to approve it.
 */
class BookingPendingMail extends BookingMail
{
    /**
     * The subject line of the message.
     */
    protected function subjectLine(): string
    {
        return __('Request received: :service', ['service' => $this->booking->service->name]);
    }

    /**
     * Build the message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.customer.booking-pending',
            with: [
                'booking' => $this->booking,
                'tenant' => $this->booking->tenant,
                'timezone' => $this->booking->customer_timezone,
                'manageUrl' => $this->manageUrl(),
            ],
        );
    }
}
