<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Proves that a portal's SMTP settings actually work.
 *
 * Deliberately not queued: the person pressing the button has to see the
 * failure, not discover it weeks later through undelivered bookings.
 */
class MailerTestMail extends Mailable
{
    public function __construct(public Tenant $tenant) {}

    /**
     * Build the message envelope.
     */
    public function envelope(): Envelope
    {
        $replyTo = $this->tenant->notificationReplyTo();

        return new Envelope(
            from: new Address($this->tenant->mailFromAddress(), $this->tenant->name),
            replyTo: $replyTo === null ? [] : [new Address($replyTo, $this->tenant->name)],
            subject: __('Test email from :name', ['name' => $this->tenant->name]),
        );
    }

    /**
     * Build the message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.mailer-test',
            with: ['tenant' => $this->tenant],
        );
    }
}
