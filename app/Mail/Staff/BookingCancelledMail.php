<?php

namespace App\Mail\Staff;

use App\Mail\BookingMail;
use Illuminate\Mail\Mailables\Content;

/**
 * Tells the operator the customer cancelled.
 */
class BookingCancelledMail extends BookingMail
{
    /**
     * The subject line of the message.
     */
    protected function subjectLine(): string
    {
        return __('Cancelled: :customer, :date', ['customer' => $this->booking->customer_name, 'date' => $this->booking->startsAtIn($this->booking->operator->timezone)->translatedFormat('D d M, H:i')]);
    }

    /**
     * Build the message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.staff.booking-cancelled',
            with: [
                'booking' => $this->booking,
                'tenant' => $this->booking->tenant,
                'timezone' => $this->booking->operator->timezone,
                'manageUrl' => $this->manageUrl(),
            ],
        );
    }
}
