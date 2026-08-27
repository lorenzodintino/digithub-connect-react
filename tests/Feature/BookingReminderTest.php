<?php

use App\Enums\BookingStatus;
use App\Mail\Customer;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->tenant = Tenant::factory()->create([
        'timezone' => 'Europe/Rome',
        'reminder_hours_before' => 24,
    ]);

    $this->service = Service::factory()->for($this->tenant)->create(['duration_minutes' => 30]);
    $this->operator = User::factory()->operator($this->tenant)->create();
    $this->service->operators()->attach($this->operator);
});

/**
 * A confirmed booking starting in the given number of hours, booked long ago.
 */
function bookingIn(float $hours, array $attributes = []): Booking
{
    $startsAt = CarbonImmutable::now('UTC')->addMinutes((int) round($hours * 60));

    return Booking::factory()
        ->forSlot(test()->service, test()->operator, $startsAt)
        ->create([
            'created_at' => CarbonImmutable::now('UTC')->subWeek(),
            ...$attributes,
        ]);
}

test('a booking inside the reminder window is reminded once', function () {
    $booking = bookingIn(20);

    $this->artisan('bookings:send-reminders')->assertSuccessful();

    Mail::assertQueued(Customer\BookingReminderMail::class, fn ($mail) => $mail->booking->is($booking));

    expect($booking->refresh()->reminder_sent_at)->not->toBeNull();
});

test('running the command twice never sends a second reminder', function () {
    bookingIn(20);

    $this->artisan('bookings:send-reminders');
    $this->artisan('bookings:send-reminders');

    Mail::assertQueuedCount(1);
});

test('a booking still outside the window waits its turn', function () {
    bookingIn(30);

    $this->artisan('bookings:send-reminders');

    Mail::assertNothingQueued();
});

test('a booking that already started is never reminded', function () {
    bookingIn(-2);

    $this->artisan('bookings:send-reminders');

    Mail::assertNothingQueued();
});

test('cancelled and pending bookings are left alone', function () {
    bookingIn(20, ['status' => BookingStatus::Cancelled]);
    bookingIn(20, ['status' => BookingStatus::Pending, 'confirmed_at' => null]);

    $this->artisan('bookings:send-reminders');

    Mail::assertNothingQueued();
});

test('a portal with reminders switched off sends none', function () {
    $this->tenant->update(['reminder_hours_before' => null]);
    bookingIn(20);

    $this->artisan('bookings:send-reminders');

    Mail::assertNothingQueued();
});

test('a suspended portal sends none', function () {
    $this->tenant->update(['is_active' => false]);
    bookingIn(20);

    $this->artisan('bookings:send-reminders');

    Mail::assertNothingQueued();
});

test('each portal uses its own lead time', function () {
    $this->tenant->update(['reminder_hours_before' => 2]);
    bookingIn(20);

    $this->artisan('bookings:send-reminders');

    Mail::assertNothingQueued();

    $this->tenant->update(['reminder_hours_before' => 48]);

    $this->artisan('bookings:send-reminders');

    Mail::assertQueued(Customer\BookingReminderMail::class);
});

test('somebody booking inside the window is not reminded of what they just did', function () {
    // Booked ten minutes ago for two hours from now, with a 24 hour lead time.
    $booking = bookingIn(2, ['created_at' => CarbonImmutable::now('UTC')->subMinutes(10)]);

    $this->artisan('bookings:send-reminders');

    Mail::assertNothingQueued();

    // Still marked, so it is not reconsidered at every run.
    expect($booking->refresh()->reminder_sent_at)->not->toBeNull();
});

test('a late run catches up instead of skipping', function () {
    // Well inside the window: a naive "between now+24h and now+24h15m" query
    // would have missed this one entirely.
    bookingIn(3);

    $this->artisan('bookings:send-reminders');

    Mail::assertQueued(Customer\BookingReminderMail::class);
});

test('moving a booking clears the reminder so the new time is announced', function () {
    $booking = bookingIn(20);

    $this->artisan('bookings:send-reminders');
    Mail::assertQueuedCount(1);

    Mail::fake();

    $booking->refresh()->update([
        'starts_at' => CarbonImmutable::now('UTC')->addHours(18),
        'ends_at' => CarbonImmutable::now('UTC')->addHours(18)->addMinutes(30),
        'reminder_sent_at' => null,
    ]);

    $this->artisan('bookings:send-reminders');

    Mail::assertQueued(Customer\BookingReminderMail::class);
});

test('the reminder reaches the customer in their own language and timezone', function () {
    bookingIn(20, ['customer_locale' => 'it', 'customer_timezone' => 'Europe/London']);

    $this->artisan('bookings:send-reminders');

    Mail::assertQueued(Customer\BookingReminderMail::class, function ($mail) {
        $mail->locale('it');

        return str_contains($mail->render(), 'Ci vediamo presto')
            && str_contains($mail->render(), 'Europe/London');
    });
});
