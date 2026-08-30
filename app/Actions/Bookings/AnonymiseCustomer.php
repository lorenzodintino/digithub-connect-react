<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\WaitingListEntry;
use App\Support\Activity;
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

        return DB::transaction(function () use ($tenant, $email, $bookings): int {
            $bookings->each(fn (Booking $booking) => $this->scrub($booking));

            // The waiting list holds the same person, by name and email.
            WaitingListEntry::query()
                ->forTenant($tenant)
                ->where('customer_email', $email)
                ->delete();

            // The anagraphic goes with them: keeping a name and an address for
            // somebody who asked to be forgotten would defeat the point.
            Customer::query()
                ->forTenant($tenant)
                ->where('email', Customer::normaliseEmail($email))
                ->delete();

            Activity::log('customer.erased', null, ['bookings' => $bookings->count()], $tenant);

            return $bookings->count();
        });
    }

    /**
     * Strip the personal data off a single booking.
     */
    public function scrub(Booking $booking): void
    {
        // Free text the customer wrote: it goes with the rest of them.
        $booking->answers()->delete();

        $booking->update([
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => null,
            'customer_notes' => null,
            'anonymised_at' => now(),
        ]);
    }
}
