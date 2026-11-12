<?php

namespace App\Support;

/**
 * Check digits of the Italian tax identifiers: no lookup anywhere, only the
 * format and the control character or digit.
 *
 * A port of the rules of DigitHub License Manager (docs/riferimento/
 * customer-profile.ts), which must give the same answer on the same input.
 */
final class ItalianTaxId
{
    /**
     * Value of each character in an odd position (counting from 1) of a tax code.
     *
     * @var array<int|string, int>
     */
    private const ODD_VALUES = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    /**
     * Seven characters may hold a letter in place of a digit (omocodia).
     */
    private const PERSONAL_TAX_CODE = '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/D';

    /**
     * A 16-character personal tax code (codice fiscale), omocodia included.
     * Expects the code already uppercase and without spaces.
     */
    public static function isValidTaxCode(string $code): bool
    {
        if (preg_match(self::PERSONAL_TAX_CODE, $code) !== 1) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 15; $index++) {
            $char = $code[$index];

            // Positions are counted from 1: odd ones use the table, even ones the plain value.
            $sum += $index % 2 === 0
                ? self::ODD_VALUES[$char]
                : (ctype_digit($char) ? (int) $char : ord($char) - 65);
        }

        return $code[15] === chr(65 + $sum % 26);
    }

    /**
     * An 11-digit Italian VAT number (partita IVA), without the IT prefix.
     */
    public static function isValidVatNumber(string $vat): bool
    {
        if (preg_match('/^\d{11}$/D', $vat) !== 1) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 10; $index++) {
            $digit = (int) $vat[$index];

            $sum += $index % 2 === 0
                ? $digit
                : ($digit * 2 > 9 ? $digit * 2 - 9 : $digit * 2);
        }

        return (10 - $sum % 10) % 10 === (int) $vat[10];
    }
}
