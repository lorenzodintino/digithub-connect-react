<?php

namespace App\Enums;

/**
 * The colour palette of a business: its booking page and its back-office.
 *
 * It sets the greys, the surfaces and the accent of the interface; the brand
 * colour of the business still wins on the booking page (buttons, rings, the
 * chosen day). Light or dark is the choice of each person, not of the business.
 * Basic is the neutral black and white of shadcn/ui. The CSS side lives in resources/css/app.css and resources/css/palettes.css.
 */
enum Palette: string
{
    case Basic = 'basic';
    case Oltremare = 'oltremare';
    case Lampone = 'lampone';

    /**
     * Every palette as the interface lists it.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $palette) => ['value' => $palette->value, 'label' => $palette->label()], self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Basic => __('Mono'),
            self::Oltremare => __('Marine'),
            self::Lampone => __('Berry'),
        };
    }
}
