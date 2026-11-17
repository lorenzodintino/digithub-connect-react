<?php

namespace App\Actions\Showcase;

use App\Models\ShowcaseMailSettings;

class SaveShowcaseMail
{
    /**
     * Save where the demo requests go and which server sends them.
     *
     * Changed server settings are unproven settings: the requests keep leaving
     * through the platform until a test goes through again. Saving only the
     * recipient does not put the server in doubt.
     *
     * @param  array<string, mixed>  $validated  see ShowcaseMailRequest::rules()
     */
    public function handle(ShowcaseMailSettings $settings, array $validated): void
    {
        $encryption = ($validated['mail_encryption'] ?? null) ?: 'tls';

        $attributes = [
            'recipient' => ($validated['recipient'] ?? null) ?: null,
            'mail_host' => ($validated['mail_host'] ?? null) ?: null,
            'mail_port' => filled($validated['mail_port'] ?? null) ? (int) $validated['mail_port'] : null,
            'mail_username' => ($validated['mail_username'] ?? null) ?: null,
            'mail_encryption' => $encryption === 'none' ? null : $encryption,
            'mail_from_address' => ($validated['mail_from_address'] ?? null) ?: null,
        ];

        // An empty field keeps the stored password; clearing the host clears it.
        $password = $validated['mail_password'] ?? null;
        $passwordChanged = $password !== null && $password !== '';

        if ($passwordChanged) {
            $attributes['mail_password'] = $password;
        } elseif ($attributes['mail_host'] === null) {
            $attributes['mail_password'] = null;
        }

        $serverChanged = $passwordChanged
            || $settings->mail_host !== $attributes['mail_host']
            || $settings->mail_port !== $attributes['mail_port']
            || $settings->mail_username !== $attributes['mail_username']
            || $settings->mail_encryption !== $attributes['mail_encryption']
            || $settings->mail_from_address !== $attributes['mail_from_address'];

        if ($serverChanged) {
            $attributes += [
                'mail_error' => null,
                'mail_failed_at' => null,
                'mail_verified_at' => null,
            ];
        }

        $settings->update($attributes);
    }
}
