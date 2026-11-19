<?php

namespace App\Actions\Bookings;

use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Models\Booking;
use App\Services\AgendaLock;
use App\Services\BookingNotifier;
use App\Support\Activity;

class ConfirmBooking
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    /**
     * Approve a booking that was waiting for manual confirmation.
     */
    public function handle(Booking $booking, mixed $meetingUrl = null): Booking
    {
        abort_unless($booking->awaitsConfirmation(), 403);

        AgendaLock::forTenant($booking->tenant_id, function () use ($booking, $meetingUrl): void {
            $booking->refresh()->load(['service', 'session']);
            abort_unless($booking->awaitsConfirmation(), 403);

            $locationType = $booking->locationType();

            if ($locationType === LocationType::Video && $booking->meetingUrl() === null) {
                $url = validator(
                    ['meeting_url' => $meetingUrl],
                    ['meeting_url' => ['required', 'string', 'max:2048', 'url:https']],
                    ['meeting_url.required' => __('Enter the video call link before confirming.'), 'meeting_url.url' => __('Enter a valid HTTPS video call link.')],
                    ['meeting_url' => __('Video call link')],
                )->validate()['meeting_url'];

                if ($booking->session !== null) {
                    $booking->session->update(['meeting_url' => $url, 'location_type' => $locationType]);
                } else {
                    $booking->meeting_url = $url;
                }
            }

            $booking->update([
                'location_type' => $locationType,
                'status' => BookingStatus::Confirmed,
                'confirmed_at' => now(),
                ...($locationType === LocationType::Video && $booking->rescheduled_at !== null
                    ? ['ics_sequence' => $booking->ics_sequence + 1]
                    : []),
            ]);
        });

        $booking->refresh();

        Activity::log('booking.confirmed', $booking);

        $this->notifier->confirmed($booking);

        return $booking;
    }
}
