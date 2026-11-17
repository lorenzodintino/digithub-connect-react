<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A portal open every day, with one operator on one service.
 *
 * @return array{0: Tenant, 1: Service, 2: User}
 */
function publicPortal(array $serviceAttributes = []): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome', 'name' => 'Studio Rossi']);

    $service = Service::factory()->for($tenant)->create([
        'name' => 'Prima consulenza',
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'max_advance_days' => 60,
        'requires_confirmation' => false,
        // These tests exercise the customer flows themselves; the notice has
        // its own suite in CancellationNoticeTest.
        'cancellation_notice_hours' => 0,
        ...$serviceAttributes,
    ]);

    $operator = User::factory()->operator($tenant)->create(['name' => 'Giulia Bianchi']);
    $service->operators()->attach($operator);

    foreach (range(0, 6) as $weekday) {
        $operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
        ]);
    }

    return [$tenant, $service, $operator];
}

/**
 * A slot that is free tomorrow.
 */
function freeSlot(Service $service, User $operator): CarbonImmutable
{
    $tomorrow = CarbonImmutable::now($service->tenant->timezone)->addDay()->startOfDay();

    return (new SlotFinder($service, $operator))->slotsForDay($tomorrow)[0];
}

test('the booking page renders the calendar', function () {
    [$tenant, $service] = publicPortal();

    $this->get(route('portal.service', [$tenant, $service]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/service')
            ->where('service.name', 'Prima consulenza')
            ->where('operators.0.name', 'Giulia Bianchi')
            ->has('calendar.days'));
});

test('a visitor picks a day and sees its slots in their own timezone', function () {
    [$tenant, $service] = publicPortal();
    $date = CarbonImmutable::now($tenant->timezone)->addDay()->format('Y-m-d');

    $home = $this->get(route('portal.service', [$tenant, $service, 'd' => $date]));

    expect(offeredTimes($home)[0])->toBe('09:00');

    $london = $this->get(route('portal.service', [$tenant, $service, 'd' => $date, 'tz' => 'Europe/London']));

    expect(offeredTimes($london)[0])->toBe('08:00');
});

test('a visitor books a slot and lands on the confirmation page', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);

    $response = $this->post(route('portal.service.book', [$tenant, $service]), bookingForm($slot, ['phone' => '+39 333 1234567']))
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->sole();

    $response->assertRedirect(route('portal.booking', [$tenant, $booking]));

    expect($booking->customer_name)->toBe('Mario Bianchi')
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->starts_at->equalTo($slot))->toBeTrue();
});

test('a page drawn inside another website stays embedded after booking', function () {
    [$tenant, $service, $operator] = publicPortal();

    $this->get(route('portal.service', [$tenant, $service, 'embed' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('portal.embed', true)
            ->where('portal.home_url', route('portal.show', [$tenant, 'embed' => 1]))
            ->where('service.urls.book', route('portal.service.book', [$tenant, $service, 'embed' => 1])));

    $this->post(route('portal.service.book', [$tenant, $service, 'embed' => 1]), bookingForm(freeSlot($service, $operator)))
        ->assertRedirect(route('portal.booking', [$tenant, Booking::query()->sole(), 'embed' => 1]));
});

test('the booking form is validated', function () {
    [$tenant, $service, $operator] = publicPortal();

    $this->post(route('portal.service.book', [$tenant, $service]), bookingForm(freeSlot($service, $operator), ['name' => '', 'email' => 'not-an-email']))
        ->assertSessionHasErrors(['name', 'email']);

    expect(Booking::count())->toBe(0);
});

test('losing the race for a slot shows an error instead of double booking', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);

    // Somebody else takes the slot before the form is submitted.
    Booking::factory()->forSlot($service, $operator, $slot)->create();

    $this->post(route('portal.service.book', [$tenant, $service]), bookingForm($slot, ['name' => 'Secondo Cliente', 'email' => 'secondo@example.com']))
        ->assertSessionHasErrors('slot');

    expect(Booking::count())->toBe(1);
});

test('a service needing approval produces a pending request', function () {
    [$tenant, $service, $operator] = publicPortal(['requires_confirmation' => true]);

    $this->post(route('portal.service.book', [$tenant, $service]), bookingForm(freeSlot($service, $operator)))
        ->assertSessionHasNoErrors();

    expect(Booking::query()->sole()->status)->toBe(BookingStatus::Pending);
});

test('the confirmation page is reachable only with the right token', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()->forSlot($service, $operator, freeSlot($service, $operator))->create();

    $this->get(route('portal.booking', [$tenant, $booking]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/booking')
            ->where('booking.service', 'Prima consulenza')
            ->where('booking.customer.name', $booking->customer_name));

    $otherTenant = Tenant::factory()->create();

    $this->get(route('portal.booking', [$otherTenant->slug, $booking->uid]))->assertNotFound();
    $this->post(route('portal.booking.cancel', [$otherTenant->slug, $booking->uid]))->assertNotFound();

    expect($booking->fresh()->status)->not->toBe(BookingStatus::Cancelled);
});

test('a customer cancels their own booking from the public page', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()->forSlot($service, $operator, freeSlot($service, $operator))->create();

    $this->post(route('portal.booking.cancel', [$tenant, $booking]))
        ->assertRedirect(route('portal.booking', [$tenant, $booking]))
        ->assertInertiaFlash('toast.message', 'Your booking has been cancelled.');

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_by_id)->toBeNull();
});

test('a customer moves their booking to another slot', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);
    $booking = Booking::factory()->forSlot($service, $operator, $slot)->create();

    $target = $slot->addHour();

    $this->get(route('portal.booking', [$tenant, $booking, 'reschedule' => 1, 'd' => $target->setTimezone($tenant->timezone)->format('Y-m-d')]))
        ->assertInertia(fn (Assert $page) => $page->where('reschedule.day.slots', fn ($slots) => collect($slots)->contains('value', $target->toIso8601String())));

    $this->post(route('portal.booking.reschedule', [$tenant, $booking]), ['slot' => $target->toIso8601String()])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Your booking has been moved.');

    expect($booking->refresh()->starts_at->equalTo($target))->toBeTrue()
        ->and($booking->original_starts_at->equalTo($slot))->toBeTrue();
});

test('the confirmation page offers the manage link and its QR code', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()->forSlot($service, $operator, freeSlot($service, $operator))->create();

    $this->get(route('portal.booking', [$tenant, $booking]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('booking.manage.url', route('portal.booking', [$tenant, $booking]))
            ->where('booking.manage.qr', fn (string $qr) => str_contains($qr, '<svg') && str_contains($qr, '#3a5cff')));
});

test('the QR can be downloaded under a name that identifies the booking', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()->forSlot($service, $operator, freeSlot($service, $operator))->create();

    $this->get(route('portal.booking', [$tenant, $booking]))
        ->assertInertia(fn (Assert $page) => $page->where('booking.manage.qr_file_name', 'studio-rossi-prima-consulenza-'.$booking->uid.'.png'));
});

test('the manage link and its QR survive a reschedule unchanged', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);
    $booking = Booking::factory()->forSlot($service, $operator, $slot)->create();

    $before = $this->get(route('portal.booking', [$tenant, $booking]))->inertiaProps('booking.manage');

    $target = $slot->addHour();

    $this->post(route('portal.booking.reschedule', [$tenant, $booking]), ['slot' => $target->toIso8601String()])->assertSessionHasNoErrors();

    // The code encodes the link, not the appointment, so it keeps working.
    expect($this->get(route('portal.booking', [$tenant, $booking]))->inertiaProps('booking.manage'))->toBe($before)
        ->and($booking->refresh()->starts_at->equalTo($target))->toBeTrue();
});

test('a cancelled booking no longer offers the QR code', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()
        ->forSlot($service, $operator, freeSlot($service, $operator))
        ->cancelled()
        ->create();

    $this->get(route('portal.booking', [$tenant, $booking]))
        ->assertInertia(fn (Assert $page) => $page->where('booking.state', 'cancelled')->where('booking.manage', null));
});

test('a cancelled booking can no longer be cancelled or moved', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);
    $booking = Booking::factory()
        ->forSlot($service, $operator, $slot)
        ->cancelled()
        ->create();

    $this->get(route('portal.booking', [$tenant, $booking]))
        ->assertInertia(fn (Assert $page) => $page->where('booking.can', ['cancel' => false, 'reschedule' => false]));

    $this->post(route('portal.booking.cancel', [$tenant, $booking]))->assertForbidden();
    $this->post(route('portal.booking.reschedule', [$tenant, $booking]), ['slot' => $slot->addHour()->toIso8601String()])->assertForbidden();
});

test('a suspended portal hides its booking pages', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);
    $tenant->update(['is_active' => false]);

    $this->get(route('portal.service', [$tenant, $service]))->assertNotFound();
    $this->post(route('portal.service.book', [$tenant, $service]), bookingForm($slot))->assertNotFound();

    expect(Booking::count())->toBe(0);
});

test('a visitor cannot fire more than five bookings a minute', function () {
    [$tenant, $service, $operator] = publicPortal();

    $tomorrow = CarbonImmutable::now($tenant->timezone)->addDay()->startOfDay();
    $slots = (new SlotFinder($service, $operator))->slotsForDay($tomorrow);

    foreach (array_slice($slots, 0, 5) as $index => $slot) {
        $this->post(route('portal.service.book', [$tenant, $service]), bookingForm($slot, ['name' => 'Cliente '.$index, 'email' => 'cliente'.$index.'@example.test']))
            ->assertSessionHasNoErrors();
    }

    $this->post(route('portal.service.book', [$tenant, $service]), bookingForm($slots[5], ['name' => 'Cliente di troppo', 'email' => 'troppo@example.test']))
        ->assertSessionHasErrors('slot');

    expect(Booking::query()->count())->toBe(5);
});

test('a suspended portal still lets the customer manage their booking', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()->forSlot($service, $operator, freeSlot($service, $operator))->create();

    $tenant->update(['is_active' => false]);

    $this->get(route('portal.show', $tenant))->assertNotFound();

    $this->get(route('portal.booking', [$tenant, $booking]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('booking.suspended', true)
            ->where('booking.can', ['cancel' => true, 'reschedule' => false]));
});

test('a suspended portal refuses a reschedule but accepts a cancellation', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);
    $booking = Booking::factory()->forSlot($service, $operator, $slot)->create();

    $tenant->update(['is_active' => false]);

    $this->get(route('portal.booking', [$tenant, $booking, 'reschedule' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('reschedule', null));

    $this->post(route('portal.booking.reschedule', [$tenant, $booking]), ['slot' => $slot->addHour()->toIso8601String()])->assertForbidden();

    $this->post(route('portal.booking.cancel', [$tenant, $booking]))->assertRedirect();

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

test('the reschedule calendar offers the next month days it already draws', function () {
    // The last day of August draws the first days of September in the same
    // grid; the customer must be able to move onto them without paging.
    $this->travelTo(CarbonImmutable::parse('2026-08-31 08:00', 'Europe/Rome'));

    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()
        ->forSlot($service, $operator, CarbonImmutable::parse('2026-08-31 10:00', 'Europe/Rome')->setTimezone('UTC'))
        ->create(['status' => BookingStatus::Confirmed]);

    $response = $this->get(route('portal.booking', [$tenant, $booking, 'reschedule' => 1, 'd' => '2026-09-01']));

    expect(offeredTimes($response, 'reschedule.day'))->toContain('09:00');
});

test('the slot list names whose clock it is showing', function () {
    [$tenant, $service] = publicPortal();
    $date = CarbonImmutable::now($tenant->timezone)->addDay()->format('Y-m-d');

    // At home there is nothing to explain: the page compares the two zones.
    $this->get(route('portal.service', [$tenant, $service, 'd' => $date]))
        ->assertInertia(fn (Assert $page) => $page->where('filters.tz', 'Europe/Rome')->where('portalTimezone', 'Europe/Rome'));

    $this->get(route('portal.service', [$tenant, $service, 'd' => $date, 'tz' => 'America/New_York']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.tz', 'America/New_York')->where('portalTimezone', 'Europe/Rome'));

    // A zone we do not offer is not taken.
    $this->get(route('portal.service', [$tenant, $service, 'tz' => 'Mars/Olympus']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.tz', 'Europe/Rome'));
});

test('a visitor abroad sees the appointment on the portal clock as well', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);
    $date = $slot->setTimezone($tenant->timezone)->format('Y-m-d');
    $end = $slot->addMinutes(30);

    $abroad = $this->get(route('portal.service', [$tenant, $service, 'd' => $date, 'tz' => 'America/New_York']))->inertiaProps('day.slots.0');

    expect($abroad['hours'])->toBe($slot->setTimezone('America/New_York')->format('H:i').' – '.$end->setTimezone('America/New_York')->format('H:i'))
        ->and($abroad['portal_hours'])->toBe($slot->setTimezone('Europe/Rome')->format('H:i').' – '.$end->setTimezone('Europe/Rome')->format('H:i'));

    // Booking from home leaves the second line out.
    expect($this->get(route('portal.service', [$tenant, $service, 'd' => $date]))->inertiaProps('day.slots.0.portal_hours'))->toBeNull();
});
