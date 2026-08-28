<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class AnonymiseCustomer
{
    /**
     * Erase everything a portal holds about one customer.
     *
     * The bookings stay: they are the portal's own record of its work, and the
     * counts on the dashboard would lie without them. What goes is the person —
     * name, address, phone, and whatever they wrote in the notes.
     *
     * Returns how many bookings were scrubbed.
     */
    public function handle(Tenant $tenant, string $email): int
    {
        $bookings = Booking::query()
            ->forTenant($tenant)
            ->where('customer_email', $email)
            ->whereNull('anonymised_at')
            ->get();

        return DB::transaction(function () use ($bookings): int {
            $bookings->each(fn (Booking $booking) => $this->scrub($booking));

            return $bookings->count();
        });
    }

    /**
     * Strip the personal data off a single booking.
     */
    public function scrub(Booking $booking): void
    {
        $booking->update([
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => null,
            'customer_notes' => null,
            'anonymised_at' => now(),
        ]);
    }
}
