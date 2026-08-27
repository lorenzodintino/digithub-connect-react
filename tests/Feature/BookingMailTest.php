<?php

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\ConfirmBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\RescheduleBooking;
use App\Mail\Customer;
use App\Mail\Staff;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->tenant = Tenant::factory()->create([
        'name' => 'Studio Rossi',
        'timezone' => 'Europe/Rome',
        'contact_email' => 'info@studiorossi.test',
        'reply_to_email' => null,
    ]);

    $this->service = Service::factory()->for($this->tenant)->create([
        'name' => 'Prima consulenza',
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'requires_confirmation' => false,
    ]);

    $this->operator = User::factory()->operator($this->tenant)->create([
        'name' => 'Giulia Bianchi',
        'email' => 'giulia@studiorossi.test',
        'locale' => 'it',
    ]);

    $this->service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
        ]);
    }
});

/**
 * The first slot free tomorrow.
 */
function mailSlot(Service $service, User $operator): CarbonImmutable
{
    $tomorrow = CarbonImmutable::now($service->tenant->timezone)->addDay()->startOfDay();

    return (new SlotFinder($service, $operator))->slotsForDay($tomorrow)[0];
}

/**
 * Place a booking through the real action.
 */
function placeBooking(Service $service, User $operator, array $overrides = []): Booking
{
    return app(CreateBooking::class)->handle($service, $operator, mailSlot($service, $operator), [
        'name' => 'Mario Bianchi',
        'email' => 'mario@example.com',
        'phone' => '+39 333 1234567',
        'notes' => 'Prima visita',
        'timezone' => 'Europe/Rome',
        'locale' => 'it',
        ...$overrides,
    ]);
}

test('booking sends one email to the customer and one to the operator', function () {
    $booking = placeBooking($this->service, $this->operator);

    Mail::assertQueued(Customer\BookingConfirmedMail::class, fn ($mail) => $mail->hasTo('mario@example.com'));
    Mail::assertQueued(Staff\BookingPlacedMail::class, fn ($mail) => $mail->hasTo('giulia@studiorossi.test'));

    // The customer must never receive the operator's address in copy.
    Mail::assertQueued(
        Customer\BookingConfirmedMail::class,
        fn ($mail) => ! $mail->hasCc('giulia@studiorossi.test') && ! $mail->hasBcc('giulia@studiorossi.test'),
    );

    expect($booking->customer_locale)->toBe('it');
});

test('the customer sees the portal as the sender and replies reach it', function () {
    placeBooking($this->service, $this->operator);

    Mail::assertQueued(Customer\BookingConfirmedMail::class, function ($mail) {
        $envelope = $mail->envelope();

        return $envelope->from->name === 'Studio Rossi'
            && $envelope->from->address === config('mail.from.address')
            && $envelope->replyTo[0]->address === 'info@studiorossi.test';
    });
});

test('an explicit reply-to address wins over the public contact one', function () {
    $this->tenant->update(['reply_to_email' => 'prenotazioni@studiorossi.test']);

    placeBooking($this->service->fresh(), $this->operator);

    Mail::assertQueued(
        Customer\BookingConfirmedMail::class,
        fn ($mail) => $mail->envelope()->replyTo[0]->address === 'prenotazioni@studiorossi.test',
    );
});

test('a portal without any address simply has no reply-to', function () {
    $this->tenant->update(['contact_email' => null, 'reply_to_email' => null]);

    placeBooking($this->service->fresh(), $this->operator);

    Mail::assertQueued(Customer\BookingConfirmedMail::class, fn ($mail) => $mail->envelope()->replyTo === []);
});

test('a service needing approval sends the pending email instead', function () {
    $this->service->update(['requires_confirmation' => true]);

    placeBooking($this->service->fresh(), $this->operator);

    Mail::assertQueued(Customer\BookingPendingMail::class);
    Mail::assertNotQueued(Customer\BookingConfirmedMail::class);
    Mail::assertQueued(Staff\BookingPlacedMail::class);
});

test('approving a request tells the customer', function () {
    $this->service->update(['requires_confirmation' => true]);
    $booking = placeBooking($this->service->fresh(), $this->operator);

    app(ConfirmBooking::class)->handle($booking);

    Mail::assertQueued(Customer\BookingConfirmedMail::class);
});

test('a customer cancellation only tells the operator', function () {
    $booking = placeBooking($this->service, $this->operator);

    app(CancelBooking::class)->handle($booking);

    Mail::assertQueued(Staff\BookingCancelledMail::class, fn ($mail) => $mail->hasTo('giulia@studiorossi.test'));
    Mail::assertNotQueued(Customer\BookingCancelledMail::class);
});

test('a portal cancellation only tells the customer', function () {
    $manager = User::factory()->manager($this->tenant)->create();
    $booking = placeBooking($this->service, $this->operator);

    app(CancelBooking::class)->handle($booking, $manager, 'Chiusura straordinaria');

    Mail::assertQueued(Customer\BookingCancelledMail::class, fn ($mail) => $mail->hasTo('mario@example.com'));
    Mail::assertNotQueued(Staff\BookingCancelledMail::class);
});

test('a customer reschedule only tells the operator', function () {
    $booking = placeBooking($this->service, $this->operator);

    app(RescheduleBooking::class)->handle($booking, $booking->starts_at->addHour());

    Mail::assertQueued(Staff\BookingRescheduledMail::class);
    Mail::assertNotQueued(Customer\BookingRescheduledMail::class);
});

test('a portal reschedule only tells the customer', function () {
    $manager = User::factory()->manager($this->tenant)->create();
    $booking = placeBooking($this->service, $this->operator);

    app(RescheduleBooking::class)->handle($booking, $booking->starts_at->addHour(), $manager);

    Mail::assertQueued(Customer\BookingRescheduledMail::class);
    Mail::assertNotQueued(Staff\BookingRescheduledMail::class);
});

test('the customer email is written in the language they booked in', function () {
    placeBooking($this->service, $this->operator, ['locale' => 'it']);

    Mail::assertQueued(Customer\BookingConfirmedMail::class, function ($mail) {
        $mail->locale('it');

        return str_contains($mail->render(), 'La tua prenotazione è confermata');
    });
});

test('the operator email is written in their own account language', function () {
    $this->operator->update(['locale' => 'en']);

    placeBooking($this->service, $this->operator->fresh(), ['locale' => 'it']);

    Mail::assertQueued(Staff\BookingPlacedMail::class, function ($mail) {
        $mail->locale('en');

        return str_contains($mail->render(), 'New booking in your agenda');
    });
});

test('the customer email carries the manage link and the operator one does not', function () {
    $booking = placeBooking($this->service, $this->operator);
    $url = route('portal.booking', [$this->tenant, $booking]);

    Mail::assertQueued(Customer\BookingConfirmedMail::class, fn ($mail) => str_contains($mail->render(), $url));
    Mail::assertQueued(Staff\BookingPlacedMail::class, fn ($mail) => ! str_contains($mail->render(), $url));
});

test('the operator agenda link lands on the booking it is about', function () {
    $booking = placeBooking($this->service, $this->operator);

    Mail::assertQueued(Staff\BookingPlacedMail::class, function ($mail) use ($booking) {
        // Filtered by customer, so the operator does not have to hunt the list.
        return str_contains($mail->render(), urlencode($booking->customer_email));
    });
});

test('the operator email warns that signing in comes first', function () {
    placeBooking($this->service, $this->operator);

    Mail::assertQueued(Staff\BookingPlacedMail::class, function ($mail) {
        // The mailable carries the operator locale, so pin it before rendering.
        $mail->locale('en');

        return str_contains($mail->render(), 'You will be asked to sign in first');
    });
});

test('the operator email carries the customer contact details and notes', function () {
    placeBooking($this->service, $this->operator);

    Mail::assertQueued(Staff\BookingPlacedMail::class, function ($mail) {
        $body = $mail->render();

        return str_contains($body, 'mario@example.com')
            && str_contains($body, '+39 333 1234567')
            && str_contains($body, 'Prima visita');
    });
});

test('every booking email is queued rather than sent inline', function () {
    placeBooking($this->service, $this->operator);

    Mail::assertNothingSent();
    Mail::assertQueuedCount(2);
});
