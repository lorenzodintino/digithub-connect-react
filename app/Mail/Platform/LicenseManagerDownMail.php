<?php

namespace App\Mail\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the administrators that the License Manager cannot be reached, or cannot be
 * used with the platform's own configuration, so they fix it fast: meanwhile the
 * portals keep working on the last answer they received.
 */
class LicenseManagerDownMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public bool $configuration, public ?CarbonImmutable $since) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->configuration
            ? __('The License Manager cannot be used with the current configuration')
            : __('The License Manager is not answering'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.platform.license-manager-down');
    }
}
