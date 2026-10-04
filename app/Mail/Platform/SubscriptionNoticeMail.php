<?php

namespace App\Mail\Platform;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a portal manager where the portal stands with what it paid for.
 *
 * Three moments, one message: the end is near, the end has passed and the
 * portal is in its days of tolerance, the portal has been suspended. Sent
 * through the platform mailer: it is the platform writing, not the portal.
 */
class SubscriptionNoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const EXPIRING = 'expiring';

    public const GRACE = 'grace';

    public const SUSPENDED = 'suspended';

    /**
     * @param  self::EXPIRING|self::GRACE|self::SUSPENDED  $notice
     */
    public function __construct(public Tenant $tenant, public string $notice) {}

    /**
     * Build the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: match ($this->notice) {
                self::EXPIRING => __('Your subscription for :portal is about to end', ['portal' => $this->tenant->name]),
                self::GRACE => __('Your subscription for :portal has ended', ['portal' => $this->tenant->name]),
                default => __(':portal has been suspended', ['portal' => $this->tenant->name]),
            },
        );
    }

    /**
     * Build the message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.platform.subscription-notice',
            with: [
                'lastPaidDay' => $this->tenant->paid_until?->translatedFormat('d F Y'),
                'suspensionDay' => $this->tenant->suspendsOn()?->translatedFormat('d F Y'),
            ],
        );
    }
}
