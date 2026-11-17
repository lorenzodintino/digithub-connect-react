<?php

namespace App\Actions\Showcase;

use App\Actions\Tenants\RecordMailFailure;
use App\Mail\ShowcaseMailTestMail;
use App\Models\ShowcaseMailSettings;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestShowcaseMailer
{
    /**
     * Send a test message through the own SMTP server of the home page.
     *
     * This is what puts that server into service: until a message goes
     * through, the requests keep leaving through the platform mailer.
     *
     * @return array{sent: bool, error: string|null}
     */
    public function handle(ShowcaseMailSettings $settings, string $recipient): array
    {
        abort_unless($settings->hasMailerSettings(), 422);

        config(['mail.mailers.'.ShowcaseMailSettings::MAILER => $settings->mailerConfig()]);

        try {
            Mail::mailer(ShowcaseMailSettings::MAILER)
                ->to($recipient)
                ->send(new ShowcaseMailTestMail($settings));
        } catch (Throwable $exception) {
            $error = RecordMailFailure::readable($exception);

            $settings->update([
                'mail_tested_at' => now(),
                'mail_error' => $error,
                'mail_failed_at' => now(),
                'mail_verified_at' => null,
            ]);

            return ['sent' => false, 'error' => $error];
        }

        $settings->update([
            'mail_tested_at' => now(),
            'mail_error' => null,
            'mail_failed_at' => null,
            'mail_verified_at' => now(),
        ]);

        return ['sent' => true, 'error' => null];
    }
}
