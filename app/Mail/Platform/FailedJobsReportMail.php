<?php

namespace App\Mail\Platform;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Tells the platform administrators what failed in the background.
 *
 * Sent through the platform mailer and never through a portal one: if a portal
 * SMTP is what broke, writing about it with that same server would fail too.
 *
 * Not queued, on purpose: the whole point is that the queue is in trouble.
 */
class FailedJobsReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, ActivityLog>  $failures
     */
    public function __construct(public Collection $failures) {}

    /**
     * Build the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':count background jobs failed', ['count' => $this->failures->count()]),
        );
    }

    /**
     * Build the message content.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.platform.failed-jobs',
            with: [
                'failures' => $this->failures,
                'byPortal' => $this->failures->groupBy(fn ($failure) => $failure->tenant->name ?? __('No portal')),
            ],
        );
    }
}
