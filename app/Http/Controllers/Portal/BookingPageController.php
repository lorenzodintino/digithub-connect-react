<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\RescheduleBooking;
use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Tenant;
use App\Services\SlotFinder;
use App\Support\CalendarNotes;
use App\Support\PortalPage;
use App\Support\QrCode;
use App\Support\SlotCalendar;
use App\Support\Timezones;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page behind the unguessable link of a booking: what was booked, and
 * the customer's own way to move or cancel it.
 *
 * A suspended portal hides its public pages, but this one stays: the customer
 * must still be able to call the appointment off. Moving it is refused there.
 * `?reschedule=1` opens the calendar, with the same `month`, `d` and `tz` of
 * the service page.
 */
class BookingPageController extends Controller
{
    public function show(Request $request, Tenant $tenant, Booking $booking): Response
    {
        abort_unless($booking->tenant_id === $tenant->id, 404);

        PortalPage::speak($request, $tenant->locale);

        $timezone = SlotCalendar::timezoneOf($request, Timezones::isSupported($booking->customer_timezone) ? $booking->customer_timezone : $tenant->timezone);
        $service = $booking->service;
        $start = $booking->startsAtIn($timezone);
        $manageUrl = route('portal.booking', [$tenant, $booking]);
        $rescheduling = $request->boolean('reschedule') && $this->canReschedule($tenant, $booking);

        return Inertia::render('portal/booking', [
            'portal' => PortalPage::layout($tenant, $request),
            'booking' => [
                'uid' => $booking->uid,
                'state' => match (true) {
                    $booking->status === BookingStatus::Cancelled => 'cancelled',
                    $booking->awaitsConfirmation() => 'pending',
                    default => 'confirmed',
                },
                'service' => $service->name,
                'color' => $service->displayColor(),
                'date' => $start->translatedFormat('l d F Y'),
                'hours' => $start->format('H:i').' – '.$booking->endsAtIn($timezone)->format('H:i'),
                'timezone' => $timezone,
                'operator' => $booking->operator->name,
                'location' => ['label' => $service->location_type->label(), 'value' => $service->location_value],
                'price' => $booking->formattedPrice(),
                'customer' => ['name' => $booking->customer_name, 'email' => $booking->customer_email],
                'phases' => $booking->phases->map(fn ($phase) => [
                    'service' => $phase->service_name,
                    'hours' => $phase->starts_at->setTimezone($timezone)->format('H:i').' – '.$phase->ends_at->setTimezone($timezone)->format('H:i'),
                    'pause' => $phase->pause_after_minutes > 0 ? trans_choice('one minute pause|:count minutes pause', $phase->pause_after_minutes) : null,
                ])->values()->all(),
                'moved_from' => $booking->rescheduled_at && $booking->original_starts_at
                    ? $booking->original_starts_at->setTimezone($timezone)->translatedFormat('d M Y, H:i')
                    : null,
                'cancellation_reason' => $booking->status === BookingStatus::Cancelled ? $booking->cancellation_reason : null,
                'cancellable' => $booking->isCancellable(),
                'can' => [
                    'cancel' => $booking->isCancellableByCustomer(),
                    'reschedule' => $this->canReschedule($tenant, $booking),
                ],
                'change_deadline' => $booking->isCancellableByCustomer()
                    ? $booking->customerChangeDeadline()?->setTimezone($timezone)->translatedFormat('D d M, H:i')
                    : null,
                'notice_hours' => (int) $service->bookingConfiguration($booking)->cancellation_notice_hours,
                'suspended' => ! $tenant->is_active,
                'contact' => ['phone' => $tenant->contact_phone, 'email' => $tenant->contact_email],
                // The link and its QR, never the appointment details: if the
                // booking is moved or cancelled the same code keeps pointing at
                // the current state.
                'manage' => $booking->isCancellable() ? [
                    'url' => $manageUrl,
                    'qr' => QrCode::svg($manageUrl, 160),
                    'qr_file_name' => Str::slug($tenant->name.'-'.$service->name).'-'.$booking->uid.'.png',
                ] : null,
                'calendar_links' => $booking->isCancellable() && $booking->status !== BookingStatus::Pending ? $this->calendarLinks($tenant, $booking, $manageUrl) : null,
                'book_again_url' => route('portal.show', [$tenant, ...PortalPage::keepEmbed($request)]),
                'urls' => [
                    'cancel' => route('portal.booking.cancel', [$tenant, $booking, ...PortalPage::keepEmbed($request)]),
                    'reschedule' => route('portal.booking.reschedule', [$tenant, $booking, ...PortalPage::keepEmbed($request)]),
                ],
            ],
            'reschedule' => $rescheduling ? $this->rescheduleCalendar($request, $tenant, $booking, $timezone) : null,
            'filters' => [
                'month' => SlotCalendar::monthOf($request, $tenant)->format('Y-m'),
                'd' => $this->dateFilter($request, 'd'),
                'tz' => $timezone,
            ],
            'timezones' => Timezones::options(),
            'portalTimezone' => $tenant->timezone,
        ]);
    }

    /**
     * Move the booking to the time the customer picked.
     */
    public function reschedule(Request $request, Tenant $tenant, Booking $booking, RescheduleBooking $rescheduleBooking): RedirectResponse
    {
        abort_unless($booking->tenant_id === $tenant->id, 404);
        abort_unless($booking->isCancellableByCustomer() && $tenant->is_active, 403);

        // A seat on a class is not moved: the class is the appointment.
        abort_if($booking->service->isGroup(), 422);

        PortalPage::speak($request, $tenant->locale);

        $slot = $request->validate(['slot' => ['required', 'date']], attributes: ['slot' => __('time')])['slot'];

        try {
            $rescheduleBooking->handle($booking, CarbonImmutable::parse($slot, 'UTC'));
        } catch (SlotUnavailableException $exception) {
            throw ValidationException::withMessages(['slot' => $exception->getMessage()]);
        }

        Toast::success(__('Your booking has been moved.'));

        return to_route('portal.booking', [$tenant, $booking, ...PortalPage::keepEmbed($request)]);
    }

    /**
     * Cancel the booking on behalf of the customer, while the notice allows it.
     */
    public function cancel(Request $request, Tenant $tenant, Booking $booking, CancelBooking $cancelBooking): RedirectResponse
    {
        abort_unless($booking->tenant_id === $tenant->id, 404);
        abort_unless($booking->isCancellableByCustomer(), 403);

        PortalPage::speak($request, $tenant->locale);

        $cancelBooking->handle($booking);

        Toast::success(__('Your booking has been cancelled.'));

        return to_route('portal.booking', [$tenant, $booking, ...PortalPage::keepEmbed($request)]);
    }

    private function canReschedule(Tenant $tenant, Booking $booking): bool
    {
        return $booking->isCancellableByCustomer() && $tenant->is_active && ! $booking->service->isGroup();
    }

    /**
     * The calendar of the times the booking could move to, itself excluded.
     *
     * @return array<string, mixed>
     */
    private function rescheduleCalendar(Request $request, Tenant $tenant, Booking $booking, string $timezone): array
    {
        $service = $booking->service->bookingConfiguration($booking);
        $calendar = new SlotCalendar($tenant, SlotCalendar::monthOf($request, $tenant), $timezone);
        [$from, $to] = $calendar->range();

        $slots = (new SlotFinder($service, $booking->operator))->ignoring($booking)->slotsBetween($from, $to);
        $date = $this->dateFilter($request, 'd');

        return [
            'calendar' => $calendar->month($slots, [], CalendarNotes::forService($booking->service, $from, $to)),
            'day' => $date === '' ? null : $calendar->day($date, $slots[$date] ?? [], (int) $service->duration_minutes),
        ];
    }

    /**
     * Links that put the appointment into the customer's own calendar.
     *
     * Google and Outlook take a plain URL; Apple and everything else get the
     * same `.ics` file, inline for Apple so iOS opens it in the Calendar app
     * instead of parking it in Files. Outlook needs two links: personal and
     * Microsoft 365 work accounts live on different hosts.
     *
     * @return array{google: string, outlook: string, outlook_work: string, apple: string, download: string}
     */
    private function calendarLinks(Tenant $tenant, Booking $booking, string $manageUrl): array
    {
        $service = $booking->service;

        $title = $service->name.' · '.$tenant->name;
        $details = __('With :name', ['name' => $booking->operator->name])."\n".$manageUrl;
        $location = $service->location_value
            ? $service->location_type->label().' · '.$service->location_value
            : $service->location_type->label();

        $startUtc = $booking->starts_at->setTimezone('UTC');
        $endUtc = $booking->ends_at->setTimezone('UTC');

        $outlookQuery = http_build_query([
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => $title,
            'startdt' => $startUtc->toIso8601ZuluString(),
            'enddt' => $endUtc->toIso8601ZuluString(),
            'body' => $details,
            'location' => $location,
        ]);

        return [
            'google' => 'https://calendar.google.com/calendar/render?'.http_build_query([
                'action' => 'TEMPLATE',
                'text' => $title,
                'dates' => $startUtc->format('Ymd\THis\Z').'/'.$endUtc->format('Ymd\THis\Z'),
                'details' => $details,
                'location' => $location,
            ]),
            'outlook' => 'https://outlook.live.com/calendar/0/deeplink/compose?'.$outlookQuery,
            'outlook_work' => 'https://outlook.office.com/calendar/0/deeplink/compose?'.$outlookQuery,
            'apple' => route('portal.booking.calendar', [$tenant, $booking, 'inline' => 1]),
            'download' => route('portal.booking.calendar', [$tenant, $booking]),
        ];
    }
}
