<?php

namespace App\Actions\Resources;

use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Services\AgendaLock;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BlockResource
{
    /**
     * Take a resource out of use for a while, maintenance or repairs.
     *
     * The times arrive in the portal's timezone, as the manager reads them on
     * the wall clock of the business, and are stored in UTC. A period that
     * overlaps a booking holding the resource is refused under the portal lock.
     */
    public function handle(Resource $resource, string $startsAt, string $endsAt, ?string $reason): ResourceBlock
    {
        $timezone = $resource->tenant->timezone;
        $start = CarbonImmutable::parse($startsAt, $timezone)->utc();
        $end = CarbonImmutable::parse($endsAt, $timezone)->utc();

        $block = AgendaLock::forTenant($resource->tenant_id, function () use ($resource, $start, $end, $reason): ResourceBlock {
            if ($resource->hasUpcomingAllocations($start, $end)) {
                throw ValidationException::withMessages([
                    'starts_at' => __('This period overlaps a resource booking. Cancel or move it first.'),
                ]);
            }

            return $resource->blocks()->create([
                'tenant_id' => $resource->tenant_id,
                'starts_at' => $start,
                'ends_at' => $end,
                'reason' => $reason,
            ]);
        });

        Activity::log('resource.blocked', $resource, ['block_id' => $block->id]);

        return $block;
    }

    /**
     * Give the resource back: freeing time needs no lock.
     */
    public function remove(ResourceBlock $block): void
    {
        Activity::log('resource.unblocked', $block->resource, ['block_id' => $block->id]);

        $block->delete();
    }
}
