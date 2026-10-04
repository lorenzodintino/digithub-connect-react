<?php

namespace App\Services;

use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\ResourceAllocation;
use App\Models\ResourceBlock;
use App\Models\Service;
use App\Models\ServiceResourceRequirement;
use App\Models\ServiceSession;
use Carbon\CarbonImmutable;

class ResourceAvailability
{
    /** @var array<int, array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>> */
    private array $busy = [];

    /** @var array<int, array{requirement: ServiceResourceRequirement, offset: int, duration: int, phase: int|null, position: int}> */
    private array $requirements = [];

    /** @var array<string, true> */
    private array $failedAssignments = [];

    public function __construct(private readonly Service $service, private readonly ?Booking $ignoredBooking = null)
    {
        if ($service->is_package) {
            $service->loadMissing('packageItems.service.resourceRequirements.resources');
            $offset = 0;
            foreach ($service->packageItems as $phase => $item) {
                foreach ($item->service->resourceRequirements as $requirement) {
                    $this->requirements[] = ['requirement' => $requirement, 'offset' => $offset, 'duration' => $item->service->duration_minutes, 'phase' => $phase, 'position' => count($this->requirements)];
                }
                $offset += $item->service->duration_minutes + $item->pause_after_minutes;
            }
        } else {
            foreach ($service->resourceRequirements()->with('resources')->get() as $requirement) {
                $this->requirements[] = ['requirement' => $requirement, 'offset' => 0, 'duration' => $service->duration_minutes, 'phase' => null, 'position' => $requirement->position];
            }
        }
    }

    public function preload(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->busy = [];
        $ids = collect($this->requirements)->flatMap(fn ($task) => $task['requirement']->resources->pluck('id'))->unique()->all();

        if ($ids === []) {
            return;
        }

        $blocks = ResourceBlock::query()->where('tenant_id', $this->service->tenant_id)
            ->whereIn('resource_id', $ids)->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get();
        $allocations = ResourceAllocation::query()->where('tenant_id', $this->service->tenant_id)
            ->whereIn('resource_id', $ids)->blocking()
            ->when($this->ignoredBooking, fn ($query) => $query->where(fn ($owner) => $owner
                ->whereNull('booking_id')->orWhere('booking_id', '!=', $this->ignoredBooking->id)))
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get();

        foreach ($blocks as $period) {
            $this->busy[$period->resource_id][] = [$period->starts_at, $period->ends_at];
        }
        foreach ($allocations as $period) {
            if ($period->resource_id !== null) {
                $this->busy[$period->resource_id][] = [$period->starts_at, $period->ends_at];
            }
        }
    }

    /**
     * Null means a missing requirement; an empty array means no resources needed.
     *
     * @return array<int, array<string, mixed>>|null
     */
    public function assignmentAt(CarbonImmutable $startsAt): ?array
    {
        $this->failedAssignments = [];
        $choices = [];
        foreach ($this->requirements as $task) {
            $alternatives = [];
            foreach ($task['requirement']->resources->sortBy('id') as $resource) {
                $from = $startsAt->utc()->addMinutes($task['offset'])->subMinutes($resource->preparation_minutes);
                $to = $startsAt->utc()->addMinutes($task['offset'] + $task['duration'] + $resource->cleanup_minutes);
                if ($resource->is_active && $resource->tenant_id === $this->service->tenant_id && $this->isFree($resource->id, $from, $to)) {
                    $alternatives[] = [
                        'tenant_id' => $this->service->tenant_id, 'resource_id' => $resource->id,
                        'resource_name' => $resource->name, 'requirement_name' => $task['requirement']->name,
                        'position' => $task['position'], 'phase_position' => $task['phase'],
                        'preparation_minutes' => $resource->preparation_minutes, 'cleanup_minutes' => $resource->cleanup_minutes,
                        'starts_at' => $from, 'ends_at' => $to, 'released_at' => null,
                    ];
                }
            }
            if ($alternatives === []) {
                return null;
            }
            $choices[] = $alternatives;
        }

        $phases = collect($choices)->groupBy(fn (array $alternatives) => $alternatives[0]['phase_position'] ?? 'individual');
        foreach ($phases as $phaseChoices) {
            if ($phaseChoices->flatMap(fn (array $alternatives) => array_column($alternatives, 'resource_id'))->unique()->count() < $phaseChoices->count()) {
                return null;
            }
        }

        usort($choices, fn (array $left, array $right) => count($left) <=> count($right));

        return $this->chooseCompatible($choices);
    }

    /** Called only inside the agenda transaction, after checking the operator. */
    public function allocate(Booking|ServiceSession $owner): void
    {
        $this->preload($owner->starts_at->subDay(), $owner->ends_at->addDay());
        $assignment = $this->assignmentAt($owner->starts_at);

        if ($assignment === null) {
            throw SlotUnavailableException::forSlot();
        }

        $phases = $owner instanceof Booking ? $owner->phases()->pluck('id', 'position')->all() : [];
        foreach ($assignment as &$attributes) {
            $attributes['booking_phase_id'] = $attributes['phase_position'] === null ? null : ($phases[$attributes['phase_position']] ?? null);
            unset($attributes['phase_position']);
        }
        unset($attributes);
        $owner->resourceAllocations()->createMany($assignment);
    }

    private function isFree(int $resourceId, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        foreach ($this->busy[$resourceId] ?? [] as [$busyFrom, $busyTo]) {
            if ($from->lessThan($busyTo) && $busyFrom->lessThan($to)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Explore alternatives when an early choice prevents a later package phase.
     *
     * @param  array<int, array<int, array<string, mixed>>>  $choices
     * @param  array<int, array<string, mixed>>  $assigned
     * @return array<int, array<string, mixed>>|null
     */
    private function chooseCompatible(array $choices, array $assigned = [], int $index = 0): ?array
    {
        if ($index === count($choices)) {
            usort($assigned, fn (array $left, array $right) => $left['position'] <=> $right['position']);

            return $assigned;
        }

        $occupied = array_map(fn (array $choice) => $choice['resource_id'].':'.$choice['phase_position'].':'.$choice['starts_at']->timestamp.':'.$choice['ends_at']->timestamp, $assigned);
        sort($occupied);
        $key = $index.'|'.implode('|', $occupied);
        if (isset($this->failedAssignments[$key])) {
            return null;
        }

        foreach ($choices[$index] as $choice) {
            $compatible = true;
            foreach ($assigned as $existing) {
                if ($choice['resource_id'] === $existing['resource_id'] && (
                    $choice['phase_position'] === $existing['phase_position']
                    || ($choice['starts_at']->lessThan($existing['ends_at']) && $existing['starts_at']->lessThan($choice['ends_at']))
                )) {
                    $compatible = false;
                    break;
                }
            }
            if ($compatible && ($result = $this->chooseCompatible($choices, [...$assigned, $choice], $index + 1)) !== null) {
                return $result;
            }
        }

        $this->failedAssignments[$key] = true;

        return null;
    }
}
