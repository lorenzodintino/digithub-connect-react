<?php

namespace App\Actions\Tenants;

use App\Mail\MailerTestMail;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestMailer
{
    /**
     * Send a test message through the portal's own SMTP server.
     *
     * The outcome is stored on the portal so a broken configuration stays
     * visible instead of silently swallowing every booking notification.
     *
     * @return array{sent: bool, error: string|null}
     */
    public function handle(Tenant $tenant, string $recipient): array
    {
        abort_unless($tenant->hasCustomMailer(), 422);

        config(["mail.mailers.{$tenant->mailerName()}" => $tenant->mailerConfig()]);

        try {
            Mail::mailer($tenant->mailerName())
                ->to($recipient)
                ->send(new MailerTestMail($tenant));
        } catch (Throwable $exception) {
            $error = RecordMailFailure::readable($exception);

            $tenant->update(['mail_tested_at' => now(), 'mail_error' => $error, 'mail_failed_at' => now()]);

            return ['sent' => false, 'error' => $error];
        }

        $tenant->update(['mail_tested_at' => now(), 'mail_error' => null, 'mail_failed_at' => null]);

        return ['sent' => true, 'error' => null];
    }
}
