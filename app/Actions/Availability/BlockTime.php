<?php

namespace App\Actions\Availability;

use App\Exceptions\PeriodHasBookingsException;
use App\Models\Booking;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\AgendaLock;
use Carbon\CarbonImmutable;

class BlockTime
{
    /**
     * Mark a period as unavailable for a staff member.
     *
     * Runs under the same lock as booking: a block takes slots away, so it must
     * not be written while somebody is booking one of them.
     *
     * @throws PeriodHasBookingsException
     */
    public function handle(User $operator, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $reason = null): TimeBlock
    {
        $startsAt = $startsAt->setTimezone('UTC');
        $endsAt = $endsAt->setTimezone('UTC');

        abort_unless($endsAt->greaterThan($startsAt), 422);

        return AgendaLock::forOperator($operator, function () use ($operator, $startsAt, $endsAt, $reason): TimeBlock {
            $conflicts = Booking::query()
                ->where('user_id', $operator->getKey())
                ->blocking()
                ->where('blocked_starts_at', '<', $endsAt)
                ->where('blocked_ends_at', '>', $startsAt)
                ->count();

            if ($conflicts > 0) {
                throw PeriodHasBookingsException::withCount($conflicts);
            }

            return TimeBlock::create([
                'user_id' => $operator->getKey(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => $reason ?: null,
            ]);
        });
    }
}
