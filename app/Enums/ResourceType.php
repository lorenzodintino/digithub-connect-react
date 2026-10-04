<?php

namespace App\Enums;

enum ResourceType: string
{
    case Cabin = 'cabin';
    case Room = 'room';
    case Station = 'station';
    case Machine = 'machine';
    case Tool = 'tool';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cabin => __('Cabin'),
            self::Room => __('Room'),
            self::Station => __('Workstation'),
            self::Machine => __('Machine'),
            self::Tool => __('Tool'),
            self::Other => __('Other'),
        };
    }
}
