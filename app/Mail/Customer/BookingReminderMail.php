<?php

namespace App\Mail\Customer;

use App\Mail\BookingMail;
use Illuminate\Mail\Mailables\Content;

/**
 * Reminds the customer shortly before their appointment.
 */
class BookingReminderMail extends BookingMail
{
    /**
     * The subject line of the message.
     */
    protected function subjectLine(): string
    {
        return __('Reminder: :service on :date', [
            'service' => $this->booking->service->name,
            'date' => $this->booking->startsAtIn($this->booking->customer_timezone)->translatedFormat('D d M, H:i'),
        ]);
    }

    /**
     * Build the message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.customer.booking-reminder',
            with: [
                'booking' => $this->booking,
                'tenant' => $this->booking->tenant,
                'timezone' => $this->booking->customer_timezone,
                'manageUrl' => $this->manageUrl(),
            ],
        );
    }
}
