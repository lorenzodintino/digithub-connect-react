<?php

namespace App\Mail;

use App\Models\ShowcaseMailSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Proves that the SMTP settings of the home page work.
 *
 * Deliberately not queued: the administrator pressing the button has to see
 * the failure at once, not discover it weeks later through requests that never
 * arrived.
 */
class ShowcaseMailTestMail extends Mailable
{
    public function __construct(public ShowcaseMailSettings $settings) {}

    public function envelope(): Envelope
    {
        // The message goes through the own server, which is not in service
        // yet: it has to carry the address that server will really use.
        return new Envelope(
            from: new Address($this->settings->ownMailFromAddress() ?? $this->settings->mailFromAddress(), config('app.name')),
            subject: __('Test email from the home page of :name', ['name' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.platform.showcase-mail-test');
    }
}
