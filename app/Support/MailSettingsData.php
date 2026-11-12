<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * What the "Email delivery" box shows: the form values and the state of the
 * portal's own server, for the manager or the administrator reading it.
 *
 * The stored password never leaves the server: the field always starts empty
 * and empty means "keep the current one".
 */
class MailSettingsData
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Tenant $tenant, User $reader): array
    {
        $state = match (true) {
            $tenant->mailDeliveryFailing() => 'failing',
            $tenant->hasMailerSettings() && ! $tenant->planAllowsCustomMailer() => 'plan',
            $tenant->hasCustomMailer() => 'own',
            $tenant->hasMailerSettings() => 'configured',
            default => 'platform',
        };

        return [
            'state' => $state,
            'canConfigure' => Gate::forUser($reader)->allows('configureMailer', $tenant),
            'usesOwnMailer' => $tenant->hasCustomMailer(),
            'hasMailerSettings' => $tenant->hasMailerSettings(),
            'failedAt' => $tenant->mail_failed_at?->setTimezone($reader->timezone)->translatedFormat('d M Y, H:i'),
            'verifiedAt' => $tenant->mail_verified_at?->setTimezone($reader->timezone)->translatedFormat('d M Y, H:i'),
            'sendingAs' => $tenant->hasCustomMailer() ? $tenant->mailFromAddress() : null,
            'error' => $tenant->mail_error,
            'errorHint' => MailFailure::explain($tenant->mail_error),
            'platformFrom' => config('mail.from.address'),
            'portalName' => $tenant->name,
            'contactEmail' => $tenant->contact_email,
            'testRecipient' => $reader->email,
            'form' => [
                'mail_host' => $tenant->mail_host ?? '',
                'mail_port' => (string) ($tenant->mail_port ?: 587),
                'mail_username' => $tenant->mail_username ?? '',
                'mail_password' => '',
                'mail_encryption' => $tenant->mail_encryption ?? 'tls',
                'mail_from_address' => $tenant->mail_from_address ?? '',
                'reply_to_email' => $tenant->reply_to_email ?? '',
                'notify_manager_on_booking' => (bool) $tenant->notify_manager_on_booking,
                'reminder_hours_before' => (string) ($tenant->reminder_hours_before ?? ''),
            ],
        ];
    }
}
