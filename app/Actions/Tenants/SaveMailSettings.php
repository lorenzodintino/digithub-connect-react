<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;

class SaveMailSettings
{
    /**
     * Save how a portal's emails leave.
     *
     * The manager keeps what they can act on — who gets a copy, when the
     * reminder goes out, where answers land. The SMTP server itself is the
     * administrator's (`configureMailer`): changed settings are unproven
     * settings, so the portal keeps sending through the platform until a test
     * goes through again.
     *
     * Shared by the portal detail of the administrator and the portal settings
     * of the manager, through the same MailSettingsController.
     *
     * @param  array<string, mixed>  $validated  see MailSettingsRequest::rulesFor()
     */
    public function handle(Tenant $tenant, array $validated, bool $canConfigure): void
    {
        $attributes = [
            'reply_to_email' => ($validated['reply_to_email'] ?? null) ?: null,
            'notify_manager_on_booking' => (bool) ($validated['notify_manager_on_booking'] ?? false),
            'reminder_hours_before' => ($validated['reminder_hours_before'] ?? null) ?: null,
        ];

        if ($canConfigure) {
            $encryption = ($validated['mail_encryption'] ?? null) ?: 'tls';

            $attributes += [
                'mail_host' => ($validated['mail_host'] ?? null) ?: null,
                'mail_port' => ($validated['mail_port'] ?? null) ?: null,
                'mail_username' => ($validated['mail_username'] ?? null) ?: null,
                'mail_encryption' => $encryption === 'none' ? null : $encryption,
                'mail_from_address' => ($validated['mail_from_address'] ?? null) ?: null,
                'mail_error' => null,
                'mail_failed_at' => null,
                'mail_verified_at' => null,
            ];

            // An empty field keeps the stored password; clearing the host clears it.
            $password = $validated['mail_password'] ?? null;

            if ($password !== null && $password !== '') {
                $attributes['mail_password'] = $password;
            } elseif ($attributes['mail_host'] === null) {
                $attributes['mail_password'] = null;
            }
        }

        $tenant->update($attributes);
    }
}
