<?php

namespace App\Enums;

/**
 * Who pays for a portal: a private person or a business.
 *
 * The values are the ones DigitHub License Manager uses for its customers.
 */
enum BillingKind: string
{
    case Person = 'PERSON';

    case Company = 'COMPANY';

    public function label(): string
    {
        return match ($this) {
            self::Person => __('Private'),
            self::Company => __('Business'),
        };
    }
}
