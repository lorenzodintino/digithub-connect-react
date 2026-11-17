<?php

namespace App\Mail\Platform;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the platform administrators that a portal took all the bookings of its
 * plan for the month: an opening to talk about a bigger one.
 */
class BookingsLimitReachedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Tenant $tenant, public ?Plan $plan, public int $bookings, public int $limit) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':portal reached the bookings of its plan', ['portal' => $this->tenant->name]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.platform.bookings-limit-reached');
    }
}
