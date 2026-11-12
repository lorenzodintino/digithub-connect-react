<?php

namespace App\Enums;

/**
 * Why the License Manager allows or refuses access (`data.access.reason`).
 */
enum LicenseReason: string
{
    case Active = 'active';

    case Grace = 'grace';

    case Expired = 'expired';

    case NotStarted = 'not_started';

    case Suspended = 'suspended';

    case Revoked = 'revoked';

    case TenantArchived = 'tenant_archived';

    case CustomerArchived = 'customer_archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Grace => __('Expired, in tolerance'),
            self::Expired => __('Expired'),
            self::NotStarted => __('Not started yet'),
            self::Suspended, self::TenantArchived, self::CustomerArchived => __('Suspended'),
            self::Revoked => __('Revoked'),
        };
    }
}
