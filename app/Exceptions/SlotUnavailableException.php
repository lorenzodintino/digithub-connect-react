<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a slot is no longer free by the time a booking is written.
 *
 * The public form re-checks availability inside the same transaction that
 * inserts the booking, so two visitors racing for the last slot get a clear
 * message instead of a double booking.
 */
class SlotUnavailableException extends RuntimeException
{
    /**
     * Build the exception with the message shown to the visitor.
     */
    public static function forSlot(): self
    {
        return new self(__('That time has just been taken. Please pick another one.'));
    }
}
