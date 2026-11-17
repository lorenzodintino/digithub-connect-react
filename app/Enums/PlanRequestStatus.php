<?php

namespace App\Enums;

/**
 * Where a request for a bigger plan stands.
 */
enum PlanRequestStatus: string
{
    /** Just sent: nobody has looked at it. */
    case New = 'new';

    /** An administrator is working on it. */
    case InProgress = 'in_progress';

    /** The plan was changed, or the request was otherwise satisfied. */
    case Handled = 'handled';

    /** Turned down, with a reason the manager can read. */
    case Rejected = 'rejected';

    /** Withdrawn by the manager who made it. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::InProgress => __('Taken in charge'),
            self::Handled => __('Handled'),
            self::Rejected => __('Rejected'),
            self::Cancelled => __('Withdrawn'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'amber',
            self::InProgress => 'sky',
            self::Handled => 'emerald',
            self::Rejected => 'red',
            self::Cancelled => 'zinc',
        };
    }

    /**
     * Still waiting for an answer: only one such request per portal at a time.
     */
    public function isOpen(): bool
    {
        return $this === self::New || $this === self::InProgress;
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::New->value, self::InProgress->value];
    }
}
