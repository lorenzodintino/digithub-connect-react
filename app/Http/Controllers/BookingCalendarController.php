<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Tenant;
use App\Support\IcsEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BookingCalendarController extends Controller
{
    /**
     * Hand the customer the calendar file for their booking.
     *
     * Reached from the confirmation page, for Apple Calendar and desktop
     * clients that have no URL to open. Guarded by the same unguessable token
     * as the page itself.
     *
     * `?inline=1` serves the same file without the attachment disposition: it
     * is what the Apple Calendar button uses, because iOS hands an inline
     * `text/calendar` response straight to the Calendar app, while an
     * attachment lands in Files and has to be opened from there.
     */
    public function __invoke(Request $request, Tenant $tenant, Booking $booking): Response
    {
        abort_unless($booking->tenant_id === $tenant->id, 404);

        $calendar = new IcsEvent($booking);

        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($calendar->toString(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => $disposition.'; filename="'.$calendar->fileName().'"',
        ]);
    }
}
