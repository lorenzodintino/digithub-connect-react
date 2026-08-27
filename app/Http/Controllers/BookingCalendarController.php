<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Tenant;
use App\Support\IcsEvent;
use Symfony\Component\HttpFoundation\Response;

class BookingCalendarController extends Controller
{
    /**
     * Hand the customer the calendar file for their booking.
     *
     * Reached from the confirmation page, for Apple Calendar and desktop
     * clients that have no URL to open. Guarded by the same unguessable token
     * as the page itself.
     */
    public function __invoke(Tenant $tenant, Booking $booking): Response
    {
        abort_unless($booking->tenant_id === $tenant->id, 404);

        $calendar = new IcsEvent($booking);

        return response($calendar->toString(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$calendar->fileName().'"',
        ]);
    }
}
