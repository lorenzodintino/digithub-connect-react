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
            $error = $this->readableError($exception);

            $tenant->update(['mail_tested_at' => now(), 'mail_error' => $error]);

            return ['sent' => false, 'error' => $error];
        }

        $tenant->update(['mail_tested_at' => now(), 'mail_error' => null]);

        return ['sent' => true, 'error' => null];
    }

    /**
     * Trim the transport error down to something a person can act on.
     *
     * Transport exceptions carry the whole SMTP conversation, which is noise
     * for the manager and can echo back credentials.
     */
    private function readableError(Throwable $exception): string
    {
        $message = trim(explode("\n", $exception->getMessage())[0]);

        return mb_substr($message, 0, 300);
    }
}
