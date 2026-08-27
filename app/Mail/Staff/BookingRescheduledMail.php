<?php

namespace App\Mail\Staff;

use App\Mail\BookingMail;
use Illuminate\Mail\Mailables\Content;

/**
 * Tells the operator the customer moved their booking.
 */
class BookingRescheduledMail extends BookingMail
{
    /**
     * The subject line of the message.
     */
    protected function subjectLine(): string
    {
        return __('Moved: :customer, :date', ['customer' => $this->booking->customer_name, 'date' => $this->booking->startsAtIn($this->booking->operator->timezone)->translatedFormat('D d M, H:i')]);
    }

    /**
     * Build the message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.staff.booking-rescheduled',
            with: [
                'booking' => $this->booking,
                'tenant' => $this->booking->tenant,
                'timezone' => $this->booking->operator->timezone,
                'manageUrl' => $this->manageUrl(),
            ],
        );
    }
}
