<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    /**
     * Human readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Confirmed => __('Confirmed'),
            self::Cancelled => __('Cancelled'),
            self::Completed => __('Completed'),
            self::NoShow => __('No show'),
        };
    }

    /**
     * Flux badge color used when rendering the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Confirmed => 'emerald',
            self::Cancelled => 'rose',
            self::Completed => 'zinc',
            self::NoShow => 'orange',
        };
    }

    /**
     * Wording of the menu entry that records this outcome.
     */
    public function outcomeLabel(): string
    {
        return match ($this) {
            self::Completed => __('Mark as completed'),
            self::NoShow => __('Mark as no show'),
            default => $this->label(),
        };
    }

    /**
     * Icon of the menu entry that records this outcome.
     */
    public function outcomeIcon(): string
    {
        return match ($this) {
            self::Completed => 'check-badge',
            self::NoShow => 'user-minus',
            default => 'flag',
        };
    }

    /**
     * Statuses that record how a past appointment went.
     *
     * @return array<int, self>
     */
    public static function outcomes(): array
    {
        return [self::Completed, self::NoShow];
    }

    /**
     * Statuses that still occupy a slot in the calendar.
     *
     * @return array<int, self>
     */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed, self::Completed];
    }
}
