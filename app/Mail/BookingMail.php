<?php

namespace App\Mail;

use App\Mail\Concerns\DeliveredByPortal;
use App\Models\Booking;
use App\Models\Tenant;
use App\Support\IcsEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Shared envelope and transport for every booking notification.
 *
 * A portal that configured its own SMTP sends as itself: same server, same
 * address, so SPF and DKIM of its domain line up. A portal that has not sends
 * through the platform mailer, where only the display name can carry its
 * identity — putting its address in From there would fail those same checks.
 */
abstract class BookingMail extends Mailable implements ShouldQueue
{
    use DeliveredByPortal, Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    /**
     * The subject line, written by each concrete mail.
     */
    abstract protected function subjectLine(): string;

    /**
     * The portal this message goes out for.
     */
    protected function portal(): Tenant
    {
        return $this->booking->tenant;
    }

    /**
     * Build the message envelope.
     */
    public function envelope(): Envelope
    {
        $tenant = $this->booking->tenant;
        $replyTo = $tenant->notificationReplyTo();

        return new Envelope(
            from: new Address($tenant->mailFromAddress(), $tenant->name),
            replyTo: $replyTo === null ? [] : [new Address($replyTo, $tenant->name)],
            subject: $this->subjectLine(),
        );
    }

    /**
     * Whether this message carries the calendar file.
     *
     * Only the ones that change what is in the customer's calendar do: a
     * request still waiting for approval has nothing to add there yet.
     */
    protected function carriesCalendarFile(): bool
    {
        return false;
    }

    /**
     * Attach the calendar file when the message warrants one.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->carriesCalendarFile()) {
            return [];
        }

        $calendar = new IcsEvent($this->booking);

        return [
            Attachment::fromData(fn () => $calendar->toString(), $calendar->fileName())
                ->withMime('text/calendar'),
        ];
    }

    /**
     * The public link that manages this booking.
     */
    protected function manageUrl(): string
    {
        return route('portal.booking', [$this->booking->tenant, $this->booking]);
    }
}
