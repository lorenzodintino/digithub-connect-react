<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Service;
use App\Models\ServicePackageItem;
use Illuminate\Database\Eloquent\Collection;

class PackagePlan
{
    /** @var Collection<int, ServicePackageItem> */
    public readonly Collection $items;

    public function __construct(private readonly Service $package, ?Booking $booking = null)
    {
        if ($booking !== null && $booking->phases()->exists()) {
            $this->items = new Collection;
            foreach ($booking->phases()->with('service.operators')->get() as $phase) {
                if ($phase->service === null) {
                    continue;
                }
                $service = clone $phase->service;
                $service->name = $phase->service_name;
                $service->duration_minutes = (int) $phase->starts_at->diffInMinutes($phase->ends_at);
                $service->buffer_before_minutes = $phase->buffer_before_minutes;
                $service->buffer_after_minutes = $phase->buffer_after_minutes;
                $service->price_cents = $phase->price_cents;
                $service->currency = $phase->currency;
                $item = new ServicePackageItem(['service_id' => $service->id, 'position' => $phase->position, 'pause_after_minutes' => $phase->pause_after_minutes]);
                $item->setRelation('service', $service);
                $this->items->push($item);
            }
        } else {
            $package->loadMissing('packageItems.service.operators');
            $this->items = $package->packageItems;
        }
    }

    public function configuration(): Service
    {
        $configured = clone $this->package;
        $configured->setRelation('packageItems', $this->items);
        $configured->duration_minutes = 0;
        $configured->capacity = 1;
        $valid = $this->items->count() >= 2;
        $price = 0;
        $offset = 0;
        $blockedStart = -$configured->buffer_before_minutes;
        $blockedEnd = 0;

        foreach ($this->items as $index => $item) {
            $service = $item->service;
            $valid = $valid && $service->tenant_id === $configured->tenant_id && $service->is_active && ! $service->is_package && ! $service->isGroup();
            $configured->min_notice_minutes = max($configured->min_notice_minutes, $service->min_notice_minutes - $offset);
            $configured->max_advance_days = min($configured->max_advance_days, $service->max_advance_days);
            $configured->cancellation_notice_hours = max($configured->cancellation_notice_hours, $service->cancellation_notice_hours);
            $configured->requires_confirmation = $configured->requires_confirmation || $service->requires_confirmation;
            $blockedStart = min($blockedStart, $offset - $service->buffer_before_minutes);
            $blockedEnd = max($blockedEnd, $offset + $service->duration_minutes + $service->buffer_after_minutes);
            $offset += $service->duration_minutes;
            if ($index < $this->items->count() - 1) {
                $offset += $item->pause_after_minutes;
            }
            $price += $service->price_cents ?? 0;
        }

        $configured->is_active = $configured->is_active && $valid;
        $configured->duration_minutes = max(5, $offset);
        $configured->buffer_before_minutes = -$blockedStart;
        $configured->buffer_after_minutes = max($configured->buffer_after_minutes, $blockedEnd - $offset);
        if ($configured->use_component_prices) {
            $configured->price_cents = $price;
        }

        return $configured;
    }

    public function recordPhases(Booking $booking): void
    {
        $startsAt = $booking->starts_at;
        foreach ($this->items as $index => $item) {
            $service = $item->service;
            $endsAt = $startsAt->addMinutes($service->duration_minutes);
            $pause = $index < $this->items->count() - 1 ? $item->pause_after_minutes : 0;
            $booking->phases()->create([
                'tenant_id' => $booking->tenant_id, 'service_id' => $service->id,
                'service_name' => $service->name, 'position' => $index,
                'pause_after_minutes' => $pause, 'starts_at' => $startsAt, 'ends_at' => $endsAt,
                'buffer_before_minutes' => $service->buffer_before_minutes,
                'buffer_after_minutes' => $service->buffer_after_minutes,
                'price_cents' => $service->price_cents, 'currency' => $service->currency,
            ]);
            $startsAt = $endsAt->addMinutes($pause);
        }
    }
}
