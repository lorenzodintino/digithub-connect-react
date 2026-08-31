<?php

namespace App\Mail\Concerns;

use App\Actions\Tenants\RecordMailFailure;
use App\Models\Tenant;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Throwable;

/**
 * Delivery through the SMTP server of a portal, and what to do when it fails.
 */
trait DeliveredByPortal
{
    /**
     * The portal this message goes out for.
     */
    abstract protected function portal(): Tenant;

    /**
     * Deliver through the portal's own SMTP server when it has one.
     *
     * This runs inside the queued job, which is the only moment the worker can
     * know which portal the message belongs to.
     *
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer)
    {
        $tenant = $this->portal();

        if ($tenant->hasCustomMailer()) {
            config(["mail.mailers.{$tenant->mailerName()}" => $tenant->mailerConfig()]);

            $this->mailer($tenant->mailerName());
        }

        return parent::send($mailer);
    }

    /**
     * Record a delivery that never happened on the portal that was sending.
     *
     * The queue calls this once the job has given up, so what the manager reads
     * on their settings screen is the state of the last real attempt.
     */
    public function failed(Throwable $exception): void
    {
        app(RecordMailFailure::class)->handle($this->portal(), $exception);
    }
}
