<?php

namespace App\Actions\Availability;

use App\Exceptions\PeriodHasBookingsException;
use App\Models\Booking;
use App\Models\PortalClosure;
use App\Models\Tenant;
use App\Support\Activity;
use Carbon\CarbonImmutable;

class CloseThePortal
{
    /**
     * Shut the portal for a stretch of whole days.
     *
     * No agenda lock here: the closure does not take a single slot away from
     * one operator, it takes whole days away from everybody, and it is refused
     * outright if anything is booked inside. Somebody booking the last free
     * minute of that range in parallel would simply make the closure fail, and
     * the manager tries again after cancelling it.
     *
     * @throws PeriodHasBookingsException
     */
    public function handle(Tenant $tenant, CarbonImmutable $startsOn, CarbonImmutable $endsOn, ?string $reason = null): PortalClosure
    {
        $startsOn = $startsOn->startOfDay();
        $endsOn = $endsOn->startOfDay();

        abort_unless($endsOn->greaterThanOrEqualTo($startsOn), 422);

        // The days are read in the portal timezone, so the window handed to the
        // booking query is the local midnight-to-midnight turned into UTC.
        $from = $startsOn->setTimezone($tenant->timezone)->startOfDay()->utc();
        $to = $endsOn->setTimezone($tenant->timezone)->endOfDay()->utc();

        $conflicts = Booking::query()
            ->forTenant($tenant)
            ->blocking()
            ->where('starts_at', '<=', $to)
            ->where('ends_at', '>=', $from)
            ->count();

        if ($conflicts > 0) {
            throw PeriodHasBookingsException::withCount($conflicts);
        }

        $closure = PortalClosure::create([
            'tenant_id' => $tenant->getKey(),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'reason' => $reason ?: null,
        ]);

        Activity::log('portal.closed', $closure, [
            'from' => $closure->starts_on->toDateString(),
            'to' => $closure->ends_on->toDateString(),
        ], $tenant);

        return $closure;
    }
}
