<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

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
        ->assertSee('Prima consulenza')
        ->assertSee('Giulia Bianchi');
});

test('a visitor picks a day and sees its slots in their own timezone', function () {
    [$tenant, $service, $operator] = publicPortal();
    $date = CarbonImmutable::now($tenant->timezone)->addDay()->format('Y-m-d');

    Livewire::test('pages::portal.service', ['tenant' => $tenant, 'service' => $service])
        ->call('selectDate', $date)
        ->assertSee('09:00')
        ->set('visitorTimezone', 'Europe/London')
        ->assertSee('08:00');
});

test('a visitor books a slot and lands on the confirmation page', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);

    Livewire::test('pages::portal.service', ['tenant' => $tenant, 'service' => $service])
        ->call('selectDate', $slot->setTimezone($tenant->timezone)->format('Y-m-d'))
        ->call('selectSlot', $slot->toIso8601String())
        ->set('customerName', 'Mario Bianchi')
        ->set('customerEmail', 'mario@example.com')
        ->set('customerPhone', '+39 333 1234567')
        ->call('book')
        ->assertHasNoErrors()
        ->assertRedirect();

    $booking = Booking::query()->sole();

    expect($booking->customer_name)->toBe('Mario Bianchi')
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->starts_at->equalTo($slot))->toBeTrue();
});

test('the booking form is validated', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);

    Livewire::test('pages::portal.service', ['tenant' => $tenant, 'service' => $service])
        ->call('selectSlot', $slot->toIso8601String())
        ->set('customerName', '')
        ->set('customerEmail', 'not-an-email')
        ->call('book')
        ->assertHasErrors(['customerName', 'customerEmail']);

    expect(Booking::count())->toBe(0);
});

test('losing the race for a slot shows an error instead of double booking', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);

    $component = Livewire::test('pages::portal.service', ['tenant' => $tenant, 'service' => $service])
        ->call('selectDate', $slot->setTimezone($tenant->timezone)->format('Y-m-d'))
        ->call('selectSlot', $slot->toIso8601String())
        ->set('customerName', 'Secondo Cliente')
        ->set('customerEmail', 'secondo@example.com');

    // Somebody else takes the slot before the form is submitted.
    Booking::factory()->forSlot($service, $operator, $slot)->create();

    $component->call('book')->assertHasErrors('selectedSlot');

    expect(Booking::count())->toBe(1);
});

test('a service needing approval produces a pending request', function () {
    [$tenant, $service, $operator] = publicPortal(['requires_confirmation' => true]);
    $slot = freeSlot($service, $operator);

    Livewire::test('pages::portal.service', ['tenant' => $tenant, 'service' => $service])
        ->call('selectSlot', $slot->toIso8601String())
        ->set('customerName', 'Mario')
        ->set('customerEmail', 'mario@example.com')
        ->call('book');

    expect(Booking::query()->sole()->status)->toBe(BookingStatus::Pending);
});

test('the confirmation page is reachable only with the right token', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()->forSlot($service, $operator, freeSlot($service, $operator))->create();

    $this->get(route('portal.booking', [$tenant, $booking]))
        ->assertOk()
        ->assertSee('Prima consulenza')
        ->assertSee($booking->customer_name);

    $otherTenant = Tenant::factory()->create();

    $this->get(route('portal.booking', [$otherTenant->slug, $booking->uid]))->assertNotFound();
});

test('a customer cancels their own booking from the public page', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()->forSlot($service, $operator, freeSlot($service, $operator))->create();

    Livewire::test('pages::portal.booking', ['tenant' => $tenant, 'booking' => $booking])
        ->call('cancel');

    expect($booking->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_by_id)->toBeNull();
});

test('a customer moves their booking to another slot', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);
    $booking = Booking::factory()->forSlot($service, $operator, $slot)->create();

    $target = $slot->addHour();

    Livewire::test('pages::portal.booking', ['tenant' => $tenant, 'booking' => $booking])
        ->call('startReschedule')
        ->call('selectDate', $target->setTimezone($tenant->timezone)->format('Y-m-d'))
        ->call('selectSlot', $target->toIso8601String())
        ->assertSet('rescheduling', false);

    expect($booking->refresh()->starts_at->equalTo($target))->toBeTrue()
        ->and($booking->original_starts_at->equalTo($slot))->toBeTrue();
});

test('the confirmation page offers the manage link and its QR code', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()->forSlot($service, $operator, freeSlot($service, $operator))->create();

    $url = route('portal.booking', [$tenant, $booking]);

    $component = Livewire::test('pages::portal.booking', ['tenant' => $tenant, 'booking' => $booking])
        ->assertSet('manageUrl', $url)
        ->assertSee($url)
        ->assertSee('Scan to reopen');

    expect($component->instance()->qrCode)->toContain('<svg');
});

test('the QR can be downloaded under a name that identifies the booking', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()->forSlot($service, $operator, freeSlot($service, $operator))->create();

    $component = Livewire::test('pages::portal.booking', ['tenant' => $tenant, 'booking' => $booking])
        ->assertSee('Download QR');

    expect($component->instance()->qrFileName)
        ->toBe('studio-rossi-prima-consulenza-'.$booking->uid.'.png');
});

test('the manage link and its QR survive a reschedule unchanged', function () {
    [$tenant, $service, $operator] = publicPortal();
    $slot = freeSlot($service, $operator);
    $booking = Booking::factory()->forSlot($service, $operator, $slot)->create();

    $component = Livewire::test('pages::portal.booking', ['tenant' => $tenant, 'booking' => $booking]);
    $urlBefore = $component->get('manageUrl');
    $qrBefore = $component->instance()->qrCode;

    $target = $slot->addHour();

    $component
        ->call('startReschedule')
        ->call('selectDate', $target->setTimezone($tenant->timezone)->format('Y-m-d'))
        ->call('selectSlot', $target->toIso8601String());

    // The code encodes the link, not the appointment, so it keeps working.
    expect($component->get('manageUrl'))->toBe($urlBefore)
        ->and($component->instance()->qrCode)->toBe($qrBefore)
        ->and($booking->refresh()->starts_at->equalTo($target))->toBeTrue();
});

test('a cancelled booking no longer offers the QR code', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()
        ->forSlot($service, $operator, freeSlot($service, $operator))
        ->cancelled()
        ->create();

    Livewire::test('pages::portal.booking', ['tenant' => $tenant, 'booking' => $booking])
        ->assertDontSee('Scan to reopen');
});

test('a cancelled booking can no longer be cancelled or moved', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()
        ->forSlot($service, $operator, freeSlot($service, $operator))
        ->cancelled()
        ->create();

    Livewire::test('pages::portal.booking', ['tenant' => $tenant, 'booking' => $booking])
        ->call('cancel')
        ->assertForbidden();
});

test('a suspended portal hides its booking pages', function () {
    [$tenant, $service] = publicPortal();
    $tenant->update(['is_active' => false]);

    $this->get(route('portal.service', [$tenant, $service]))->assertNotFound();
});

test('a visitor cannot fire more than five bookings a minute', function () {
    [$tenant, $service, $operator] = publicPortal();

    $tomorrow = CarbonImmutable::now($tenant->timezone)->addDay()->startOfDay();
    $slots = (new SlotFinder($service, $operator))->slotsForDay($tomorrow);

    foreach (array_slice($slots, 0, 5) as $index => $slot) {
        Livewire::test('pages::portal.service', ['tenant' => $tenant, 'service' => $service])
            ->set('selectedDate', $slot->setTimezone($tenant->timezone)->format('Y-m-d'))
            ->set('selectedSlot', $slot->toIso8601String())
            ->set('customerName', 'Cliente '.$index)
            ->set('customerEmail', 'cliente'.$index.'@example.test')
            ->call('book')
            ->assertHasNoErrors();
    }

    Livewire::test('pages::portal.service', ['tenant' => $tenant, 'service' => $service])
        ->set('selectedDate', $slots[5]->setTimezone($tenant->timezone)->format('Y-m-d'))
        ->set('selectedSlot', $slots[5]->toIso8601String())
        ->set('customerName', 'Cliente di troppo')
        ->set('customerEmail', 'troppo@example.test')
        ->call('book')
        ->assertHasErrors('selectedSlot');

    expect(Booking::query()->count())->toBe(5);
});

test('a suspended portal still lets the customer manage their booking', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()->forSlot($service, $operator, freeSlot($service, $operator))->create();

    $tenant->update(['is_active' => false]);

    $this->get(route('portal.show', $tenant))->assertNotFound();

    $this->get(route('portal.booking', [$tenant, $booking]))
        ->assertOk()
        ->assertSee('suspended', escape: false);
});

test('a suspended portal refuses a reschedule but accepts a cancellation', function () {
    [$tenant, $service, $operator] = publicPortal();
    $booking = Booking::factory()->forSlot($service, $operator, freeSlot($service, $operator))->create();

    $tenant->update(['is_active' => false]);

    Livewire::test('pages::portal.booking', ['tenant' => $tenant->fresh(), 'booking' => $booking])
        ->call('startReschedule')
        ->assertForbidden();

    Livewire::test('pages::portal.booking', ['tenant' => $tenant->fresh(), 'booking' => $booking])
        ->call('cancel');

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});
