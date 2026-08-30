<?php

namespace App\Mail\Customer;

use App\Models\WaitingListEntry;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells somebody on the waiting list that a time opened up.
 *
 * It does not hold the place for them: whoever books first takes it, and the
 * message says so. Holding a slot for hours on the strength of an unread email
 * would keep it empty for everybody else.
 *
 * The envelope follows the same rule as the booking mails, spelled out here
 * because this one is not about a booking: a portal with its own SMTP sends as
 * itself, the others go out through the platform with their name on the label.
 */
class WaitingListOpeningMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public WaitingListEntry $entry,
        public string $slot,
    ) {}

    /**
     * Deliver through the portal's own SMTP server when it has one.
     *
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer)
    {
        $tenant = $this->entry->tenant;

        if ($tenant->hasCustomMailer()) {
            config(["mail.mailers.{$tenant->mailerName()}" => $tenant->mailerConfig()]);

            $this->mailer($tenant->mailerName());
        }

        return parent::send($mailer);
    }

    /**
     * Build the message envelope.
     */
    public function envelope(): Envelope
    {
        $tenant = $this->entry->tenant;
        $replyTo = $tenant->notificationReplyTo();

        return new Envelope(
            from: new Address($tenant->mailFromAddress(), $tenant->name),
            replyTo: $replyTo === null ? [] : [new Address($replyTo, $tenant->name)],
            subject: __('A place opened up for :service', ['service' => $this->entry->service->name]),
        );
    }

    /**
     * Build the message content.
     */
    public function content(): Content
    {
        $tenant = $this->entry->tenant;
        $slot = CarbonImmutable::parse($this->slot)->setTimezone($this->entry->customer_timezone);

        return new Content(
            view: 'mail.customer.waiting-list-opening',
            with: [
                'entry' => $this->entry,
                'tenant' => $tenant,
                'service' => $this->entry->service,
                'slot' => $slot,
                'bookUrl' => route('portal.service', [$tenant, $this->entry->service]).'?d='.$slot->setTimezone($tenant->timezone)->format('Y-m-d'),
                'leaveUrl' => route('portal.waiting-list.leave', [$tenant, $this->entry]),
            ],
        );
    }
}
