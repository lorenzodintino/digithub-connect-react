<?php

namespace App\Support;

use App\Models\ShowcaseMailSettings;
use App\Models\User;

/**
 * What the page "Email of the home page" shows: the form values and the state
 * of the server, for the administrator reading it.
 *
 * The stored password never leaves the server: the field always starts empty
 * and empty means "keep the current one".
 */
class ShowcaseMailData
{
    /**
     * @return array<string, mixed>
     */
    public static function for(ShowcaseMailSettings $settings, User $reader): array
    {
        $state = match (true) {
            ! $settings->acceptsRequests() => 'off',
            $settings->mailDeliveryFailing() => 'failing',
            $settings->hasCustomMailer() => 'own',
            $settings->hasMailerSettings() => 'configured',
            default => 'platform',
        };

        return [
            'state' => $state,
            'hasMailerSettings' => $settings->hasMailerSettings(),
            'recipient' => $settings->recipientAddress(),
            'recipientFromEnvironment' => blank($settings->recipient) && $settings->recipientAddress() !== null,
            'failedAt' => $settings->mail_failed_at?->setTimezone($reader->timezone)->translatedFormat('d M Y, H:i'),
            'verifiedAt' => $settings->mail_verified_at?->setTimezone($reader->timezone)->translatedFormat('d M Y, H:i'),
            'sendingAs' => $settings->mailFromAddress(),
            'platformFrom' => config('mail.from.address'),
            'appName' => config('app.name'),
            'error' => $settings->mail_error,
            'errorHint' => MailFailure::explain($settings->mail_error),
            'testRecipient' => $reader->email,
            'form' => [
                'recipient' => $settings->recipient ?? '',
                'mail_host' => $settings->mail_host ?? '',
                'mail_port' => (string) ($settings->mail_port ?: 587),
                'mail_username' => $settings->mail_username ?? '',
                'mail_password' => '',
                'mail_encryption' => $settings->mail_encryption ?? 'tls',
                'mail_from_address' => $settings->mail_from_address ?? '',
            ],
        ];
    }
}
