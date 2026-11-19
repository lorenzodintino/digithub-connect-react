<?php

use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Mail\Customer\BookingConfirmedMail;
use App\Mail\Customer\BookingPendingMail;
use App\Mail\Staff\BookingRescheduledMail;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{Service, User, User}
 */
function videoService(int $capacity = 1, ?string $oldLink = null): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'UTC', 'locale' => 'en']);
    $service = Service::factory()->for($tenant)->create([
        'location_type' => LocationType::Video,
        'location_value' => $oldLink,
        'requires_confirmation' => false,
        'capacity' => $capacity,
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'min_notice_minutes' => 0,
        'buffer_after_minutes' => 0,
        'cancellation_notice_hours' => 0,
    ]);
    $operator = User::factory()->operator($tenant)->create();
    $service->operators()->attach($operator);
    $operator->availabilityRules()->create(['weekday' => 2, 'starts_at' => '09:00:00', 'ends_at' => '12:00:00']);

    return [$service, $operator, User::factory()->manager($tenant)->create()];
}

function requestVideoBooking(Service $service, CarbonImmutable $slot, string $email): Booking
{
    test()->post(route('portal.service.book', [$service->tenant, $service]), bookingForm($slot, ['email' => $email]))
        ->assertSessionHasNoErrors();

    return $service->bookings()->where('customer_email', $email)->sole();
}

test('an individual video booking stays pending until the operator provides its link', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00 UTC'));
    Mail::fake();
    [$service, $operator] = videoService(oldLink: 'https://meet.google.com/old-link');
    $slot = CarbonImmutable::parse('2026-11-17 09:00 UTC');
    $meetingUrl = 'https://meet.google.com/one-meeting';

    $this->get(route('portal.service', [$service->tenant, $service]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('service.requires_confirmation', true)
            ->where('service.location.value', null));

    $booking = requestVideoBooking($service, $slot, 'individual@example.test');
    $customerPage = route('portal.booking', [$service->tenant, $booking]);
    expect($booking->status)->toBe(BookingStatus::Pending);
    Mail::assertQueued(BookingPendingMail::class, fn (BookingPendingMail $mail) => $mail->hasTo($booking->customer_email));
    $this->get($customerPage)->assertInertia(fn (Assert $page) => $page
        ->where('booking.location.value', null)->where('booking.meeting_url', null));

    $this->actingAs($operator)->post(route('app.bookings.confirm', $booking), ['meeting_url' => $meetingUrl])
        ->assertSessionHasNoErrors();

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->meeting_url)->toBe($meetingUrl);
    Mail::assertQueued(BookingConfirmedMail::class, fn (BookingConfirmedMail $mail) => $mail->hasTo($booking->customer_email));
    $rendered = unserialize(serialize(new BookingConfirmedMail($booking)))->render();
    expect($rendered)->toContain('href="'.$meetingUrl.'"')->not->toContain('old-link');
    $this->get($customerPage)->assertInertia(fn (Assert $page) => $page->where('booking.meeting_url', $meetingUrl));

    $this->post(route('app.bookings.cancel', $booking), ['reason' => 'Customer cancelled'])
        ->assertSessionHasNoErrors();
    $this->get($customerPage)->assertInertia(fn (Assert $page) => $page->where('booking.meeting_url', null));
});

test('confirming a video booking rejects missing and invalid links without sending confirmation', function (?string $link, string $message) {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00 UTC'));
    Mail::fake();
    [$service, $operator, $manager] = videoService();
    $booking = Booking::factory()->pending()->forSlot($service, $operator, CarbonImmutable::parse('2026-11-17 09:00 UTC'))->create();

    $this->actingAs($manager)->post(route('app.bookings.confirm', $booking), ['meeting_url' => $link])
        ->assertSessionHasErrors(['meeting_url' => $message]);

    expect($booking->refresh()->status)->toBe(BookingStatus::Pending)
        ->and($booking->confirmed_at)->toBeNull()
        ->and($booking->meeting_url)->toBeNull();
    Mail::assertNotQueued(BookingConfirmedMail::class);
})->with([
    'missing' => [null, 'Enter the video call link before confirming.'],
    'malformed' => ['not-a-link', 'Enter a valid HTTPS video call link.'],
    'non-HTTPS' => ['http://meet.google.com/one-meeting', 'Enter a valid HTTPS video call link.'],
    'unsafe scheme' => ['javascript:alert(1)', 'Enter a valid HTTPS video call link.'],
]);

test('a group keeps the first confirmed link and a different session still needs its own', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00 UTC'));
    Mail::fake();
    [$service, , $manager] = videoService(capacity: 3);
    $slot = CarbonImmutable::parse('2026-11-17 09:00 UTC');
    $first = requestVideoBooking($service, $slot, 'first@example.test');
    $second = requestVideoBooking($service, $slot, 'second@example.test');
    $otherSession = requestVideoBooking($service, $slot->addHour(), 'other@example.test');
    $meetingUrl = 'https://meet.google.com/group-meeting';

    expect($first->status)->toBe(BookingStatus::Pending)
        ->and($second->status)->toBe(BookingStatus::Pending)
        ->and($second->service_session_id)->toBe($first->service_session_id)
        ->and($otherSession->service_session_id)->not->toBe($first->service_session_id);

    $this->actingAs($manager)->post(route('app.bookings.confirm', $first))
        ->assertSessionHasErrors('meeting_url');
    $this->post(route('app.bookings.confirm', $first), ['meeting_url' => $meetingUrl])
        ->assertSessionHasNoErrors();

    $secondPage = route('portal.booking', [$service->tenant, $second]);
    $this->get($secondPage)->assertInertia(fn (Assert $page) => $page->where('booking.meeting_url', null));
    $this->get(route('app.bookings', ['booking' => $second->uid]))
        ->assertInertia(fn (Assert $page) => $page->where('details.video_confirmation.meeting_url', $meetingUrl));
    Mail::assertNotQueued(BookingConfirmedMail::class, fn (BookingConfirmedMail $mail) => $mail->hasTo($second->customer_email));

    $this->post(route('app.bookings.confirm', $second), ['meeting_url' => 'https://meet.google.com/stale-form'])
        ->assertSessionHasNoErrors();
    $third = requestVideoBooking($service, $slot, 'third@example.test');
    $this->post(route('app.bookings.confirm', $third))->assertSessionHasNoErrors();
    $this->post(route('app.bookings.confirm', $otherSession))->assertSessionHasErrors('meeting_url');

    expect($first->refresh()->session->meeting_url)->toBe($meetingUrl)
        ->and($second->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($second->meeting_url)->toBeNull()
        ->and($third->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($otherSession->refresh()->status)->toBe(BookingStatus::Pending)
        ->and($otherSession->session->meeting_url)->toBeNull();
    Mail::assertQueued(BookingConfirmedMail::class, fn (BookingConfirmedMail $mail) => $mail->hasTo($second->customer_email));
    expect((new BookingConfirmedMail($second))->render())->toContain('href="'.$meetingUrl.'"');
    $this->get($secondPage)->assertInertia(fn (Assert $page) => $page->where('booking.meeting_url', $meetingUrl));
});

test('moving an individual video booking hides the old link until the new time is confirmed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00 UTC'));
    Mail::fake();
    [$service, $operator, $manager] = videoService();
    $slot = CarbonImmutable::parse('2026-11-17 09:00 UTC');
    $booking = Booking::factory()->forSlot($service, $operator, $slot)->create([
        'location_type' => LocationType::Video,
        'meeting_url' => 'https://meet.google.com/old-meeting',
    ]);
    $target = $slot->addHour();

    $this->post(route('portal.booking.reschedule', [$service->tenant, $booking]), ['slot' => $target->toIso8601String()])
        ->assertSessionHasNoErrors();

    expect($booking->refresh()->starts_at->equalTo($target))->toBeTrue()
        ->and($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->confirmed_at)->toBeNull()
        ->and($booking->meeting_url)->toBeNull();
    Mail::assertQueued(BookingRescheduledMail::class, fn (BookingRescheduledMail $mail) => $mail->hasTo($operator->email));
    $page = route('portal.booking', [$service->tenant, $booking]);
    $this->get($page)->assertInertia(fn (Assert $page) => $page->where('booking.state', 'pending')->where('booking.meeting_url', null));

    $newLink = 'https://meet.google.com/new-meeting';
    $this->actingAs($manager)->post(route('app.bookings.confirm', $booking), ['meeting_url' => $newLink])
        ->assertSessionHasNoErrors();
    $this->get($page)->assertInertia(fn (Assert $page) => $page->where('booking.meeting_url', $newLink));
});
