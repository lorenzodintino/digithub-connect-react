<?php

namespace App\Enums;

enum LocationType: string
{
    case InPerson = 'in_person';
    case Phone = 'phone';
    case Video = 'video';
    case Custom = 'custom';

    /**
     * Human readable label for the location type.
     */
    public function label(): string
    {
        return match ($this) {
            self::InPerson => __('In person'),
            self::Phone => __('Phone call'),
            self::Video => __('Video call'),
            self::Custom => __('Custom'),
        };
    }

    /**
     * Heroicon name used when rendering the location type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::InPerson => 'map-pin',
            self::Phone => 'phone',
            self::Video => 'video-camera',
            self::Custom => 'link',
        };
    }

    /**
     * Placeholder shown for the free-form location value.
     */
    public function valuePlaceholder(): string
    {
        return match ($this) {
            self::InPerson => __('Via Roma 1, Milano'),
            self::Phone => __('We will call you at the number you provide'),
            self::Video => __('https://meet.google.com/abc-defg-hij'),
            self::Custom => __('Any detail the customer needs to know'),
        };
    }
}
