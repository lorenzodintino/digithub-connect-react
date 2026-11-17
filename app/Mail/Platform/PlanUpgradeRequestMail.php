<?php

namespace App\Mail\Platform;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the platform administrators that the manager of a portal asks for a
 * bigger plan. The plan itself is changed elsewhere (by hand, or by the
 * License Manager): this message only starts the conversation.
 */
class PlanUpgradeRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public User $manager,
        public ?Plan $current,
        public Plan $requested,
        public ?string $reason,
        public ?string $note,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Plan upgrade request from :portal', ['portal' => $this->tenant->name]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.platform.plan-upgrade-request');
    }
}
