<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(10, 0);

        return [
            'uid' => Booking::newUid(),
            'service_id' => Service::factory(),
            'tenant_id' => fn (array $attributes) => Service::query()->whereKey($attributes['service_id'])->sole()->tenant_id,
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->unique()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_notes' => null,
            'customer_timezone' => 'Europe/Rome',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
            'blocked_starts_at' => $startsAt,
            'blocked_ends_at' => $startsAt->addMinutes(30),
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'ics_sequence' => 0,
            'price_cents' => null,
            'currency' => 'EUR',
        ];
    }

    /**
     * Place the booking on a concrete slot of a service, buffers included.
     */
    public function forSlot(Service $service, User $operator, CarbonImmutable $startsAt): static
    {
        $startsAt = $startsAt->setTimezone('UTC');
        $endsAt = $startsAt->addMinutes($service->duration_minutes);

        return $this->state(fn (array $attributes) => [
            'tenant_id' => $service->tenant_id,
            'service_id' => $service->getKey(),
            'user_id' => $operator->getKey(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'blocked_starts_at' => $startsAt->subMinutes($service->buffer_before_minutes),
            'blocked_ends_at' => $endsAt->addMinutes($service->buffer_after_minutes),
            'price_cents' => $service->price_cents,
            'currency' => $service->currency,
        ]);
    }

    /**
     * Indicate that the booking is waiting for approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Pending,
            'confirmed_at' => null,
        ]);
    }

    /**
     * Indicate that the booking has been cancelled and no longer blocks its slot.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }
}
