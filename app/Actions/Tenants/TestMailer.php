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
     * This is also what puts those settings into service: the portal keeps
     * sending through the platform until a message actually goes through, so a
     * configuration nobody checked cannot swallow the notifications.
     *
     * @return array{sent: bool, error: string|null}
     */
    public function handle(Tenant $tenant, string $recipient): array
    {
        abort_unless($tenant->hasMailerSettings(), 422);

        config(["mail.mailers.{$tenant->mailerName()}" => $tenant->mailerConfig()]);

        try {
            Mail::mailer($tenant->mailerName())
                ->to($recipient)
                ->send(new MailerTestMail($tenant));
        } catch (Throwable $exception) {
            $error = RecordMailFailure::readable($exception);

            // Whatever worked before does not work now: back to the platform,
            // where the emails at least leave.
            $tenant->update([
                'mail_tested_at' => now(),
                'mail_error' => $error,
                'mail_failed_at' => now(),
                'mail_verified_at' => null,
            ]);

            return ['sent' => false, 'error' => $error];
        }

        $tenant->update([
            'mail_tested_at' => now(),
            'mail_error' => null,
            'mail_failed_at' => null,
            'mail_verified_at' => now(),
        ]);

        return ['sent' => true, 'error' => null];
    }
}
