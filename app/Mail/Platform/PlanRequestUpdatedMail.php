<?php

namespace App\Mail\Platform;

use App\Models\PlanRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the manager what became of their request for a bigger plan.
 */
class PlanRequestUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PlanRequest $request) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your request for the :plan plan: :status', ['plan' => $this->request->requested_plan, 'status' => mb_strtolower($this->request->status->label())]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.platform.plan-request-updated', with: ['tenant' => $this->request->tenant]);
    }
}
