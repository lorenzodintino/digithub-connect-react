<?php

namespace App\Support;

use App\Enums\LicenseOutcome;
use Carbon\CarbonImmutable;

/**
 * The words for a refused licence: what the blocked screen says and the short
 * label the administrator page and the notices use. One place, so the
 * wording of a state reads the same everywhere.
 */
final class LicenseMessages
{
    /**
     * A short label for a block key (see LicenseStatus::blockKey()).
     */
    public static function reason(string $key): string
    {
        return match ($key) {
            'expired' => __('license expired'),
            'not_started' => __('license not started yet'),
            'suspended', 'tenant_archived', 'customer_archived' => __('license suspended'),
            'revoked' => __('license revoked'),
            'tenant_not_found' => __('portal not registered in the License Manager'),
            'no_license' => __('license not issued yet'),
            'software_inactive' => __('software disabled in the License Manager'),
            default => __('license not verifiable'),
        };
    }

    /**
     * The state of a licence in a word, for the administrator page.
     */
    public static function state(LicenseStatus $status): string
    {
        return match ($status->outcome) {
            LicenseOutcome::Licensed => $status->isExpiring() ? __('Expiring soon') : ($status->reason?->label() ?? __('Active')),
            LicenseOutcome::NotActivated => $status->notActivatedReason === 'no_license' ? __('No license issued') : __('Not registered'),
            LicenseOutcome::SoftwareInactive => __('Software disabled'),
            LicenseOutcome::Unavailable => __('Never verified'),
        };
    }

    /**
     * The colour a state is drawn in (the names ColorBadge knows).
     */
    public static function color(LicenseStatus $status): string
    {
        return match (true) {
            $status->outcome === LicenseOutcome::Licensed && $status->allowed && ! $status->isExpiring() && ! $status->isInGrace() => 'emerald',
            $status->outcome === LicenseOutcome::Licensed && $status->allowed => 'amber',
            $status->outcome === LicenseOutcome::Unavailable => 'zinc',
            default => 'red',
        };
    }

    /**
     * The length of a renewal as the manager reads it: «12 months», or «Lifetime» when it has no months.
     */
    public static function period(?int $months): string
    {
        return $months === null ? __('Lifetime') : trans_choice(':count month|:count months', $months);
    }

    /**
     * An amount in cents as the administrator reads it.
     */
    public static function money(?int $cents, ?string $currency): ?string
    {
        if ($cents === null) {
            return null;
        }

        return ($currency === 'EUR' || $currency === null ? '€ ' : $currency.' ').number_format($cents / 100, 2, ',', '.');
    }

    /**
     * The sentence of the blocked screen, with dates in the portal's timezone.
     */
    public static function explain(LicenseStatus $status, string $timezone): string
    {
        $key = $status->blockKey() ?? 'unverifiable';

        return match ($key) {
            'expired' => __('The license expired on :date.', ['date' => self::date($status->expiresAt, $timezone)]),
            'not_started' => __('The license starts on :date.', ['date' => self::date($status->startsAt, $timezone)]),
            'suspended', 'tenant_archived', 'customer_archived' => __('The license is suspended. Contact the platform administrator.'),
            'revoked' => __('The license has been revoked.'),
            'tenant_not_found' => __('The license of this portal is not active yet.'),
            'no_license' => __('The license of this portal has not been issued yet.'),
            'software_inactive' => __('The service is not available at the moment.'),
            default => __('We cannot verify the license right now. Try again in a few minutes.'),
        };
    }

    /**
     * The title of the blocked screen.
     */
    public static function title(LicenseStatus $status): string
    {
        return match ($status->blockKey()) {
            'tenant_not_found', 'no_license' => __('License not active'),
            'unverifiable', null => __('License not verifiable'),
            default => __('License not valid'),
        };
    }

    private static function date(?CarbonImmutable $date, string $timezone): string
    {
        return $date === null ? '—' : $date->setTimezone($timezone)->translatedFormat('d M Y');
    }
}
