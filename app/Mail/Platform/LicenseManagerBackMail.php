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
 * Tells the administrators that the License Manager answers again.
 */
class LicenseManagerBackMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ?CarbonImmutable $since) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('The License Manager answers again'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.platform.license-manager-back');
    }
}
