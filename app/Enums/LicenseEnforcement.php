<?php

namespace App\Enums;

/**
 * How far the platform goes with the licence of a portal (`LICENSE_ENFORCEMENT`).
 */
enum LicenseEnforcement: string
{
    /** Nothing changes and nothing is called automatically; only the administrator's «Check now». */
    case Off = 'off';

    /** The License Manager is checked and what it would block is logged; nobody is blocked. */
    case Observe = 'observe';

    /** The License Manager decides access and plan. */
    case On = 'on';

    /**
     * An unknown value is read as off: a typo must never lock customers out.
     */
    public static function fromConfig(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom(strtolower(trim($value))) ?? self::Off) : self::Off;
    }
}
