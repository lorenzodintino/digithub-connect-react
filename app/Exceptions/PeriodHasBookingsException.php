<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a period cannot be blocked because bookings already live in it.
 *
 * Blocking time over a booked slot would leave the agenda contradicting
 * itself, so the write is refused and the conflicting bookings are named.
 */
class PeriodHasBookingsException extends RuntimeException
{
    /**
     * Build the exception for a period that would swallow existing bookings.
     */
    public static function withCount(int $count): self
    {
        return new self(trans_choice('There is one booking in that period. Cancel or move it first.|There are :count bookings in that period. Cancel or move them first.', $count));
    }

    /**
     * Build the exception for new hours that would leave bookings outside them.
     */
    public static function outsideNewHours(int $count): self
    {
        return new self(trans_choice('There is one booking outside the new hours. Cancel or move it first.|There are :count bookings outside the new hours. Cancel or move them first.', $count));
    }
}
