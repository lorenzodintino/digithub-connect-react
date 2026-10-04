<?php

namespace App\Enums;

/**
 * Where a portal stands with what it paid for.
 */
enum SubscriptionState: string
{
    /** No end date: a free or open-ended portal. */
    case Open = 'open';

    /** Paid, and the end is still far. */
    case Active = 'active';

    /** Paid, but the end is close enough to remind the manager. */
    case Expiring = 'expiring';

    /** Past the paid date, inside the days of tolerance. */
    case Grace = 'grace';

    /** Past the tolerance too: the portal is due to be suspended. */
    case Lapsed = 'lapsed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('No expiry'),
            self::Active => __('Paid'),
            self::Expiring => __('Expiring soon'),
            self::Grace => __('Expired, in tolerance'),
            self::Lapsed => __('Expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open, self::Active => 'emerald',
            self::Expiring => 'amber',
            self::Grace, self::Lapsed => 'red',
        };
    }
}
