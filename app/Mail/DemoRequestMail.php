<?php

namespace App\Mail;

use App\Actions\Showcase\RecordShowcaseMailFailure;
use App\Models\ShowcaseMailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * The demo request of the home page, as it reaches the administrators: every
 * field the visitor filled in, with their address as Reply-To so the answer is
 * one click away.
 *
 * Queued, so the visitor never waits for the SMTP server. It carries plain
 * strings only: nothing is stored on the platform besides the queue payload.
 */
class DemoRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $requesterName,
        public string $requesterEmail,
        public ?string $requesterBusiness,
        public ?string $requestedPlan,
        public ?string $requesterMessage,
        public string $pageLanguage,
        public string $consentGivenAt,
    ) {}

    /**
     * Deliver through the own SMTP server when it has proved itself.
     *
     * This runs inside the queued job, so the settings read here are the ones
     * in force when the message actually leaves.
     *
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer)
    {
        $settings = ShowcaseMailSettings::current();

        if ($settings->hasCustomMailer()) {
            config(['mail.mailers.'.ShowcaseMailSettings::MAILER => $settings->mailerConfig()]);

            $this->mailer(ShowcaseMailSettings::MAILER);
        }

        return parent::send($mailer);
    }

    /**
     * Record a delivery that never happened, so the administrator can read it on the settings page.
     */
    public function failed(Throwable $exception): void
    {
        app(RecordShowcaseMailFailure::class)->handle($exception);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(ShowcaseMailSettings::current()->mailFromAddress(), config('app.name')),
            replyTo: [new Address($this->requesterEmail, $this->requesterName)],
            subject: __('Demo request from :name', ['name' => $this->requesterName]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.platform.demo-request');
    }
}
