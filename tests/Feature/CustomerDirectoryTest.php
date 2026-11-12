<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();

    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
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
 * Place a booking through the public page, as a customer would.
 */
function bookAs(string $name, string $email, int $slotIndex = 0): void
{
    $tomorrow = CarbonImmutable::now(test()->tenant->timezone)->addDay()->startOfDay();
    $slot = (new SlotFinder(test()->service, test()->operator))->slotsForDay($tomorrow)[$slotIndex];

    test()->post(route('portal.service.book', [test()->tenant, test()->service]), bookingForm($slot, ['name' => $name, 'email' => $email]))
        ->assertSessionHasNoErrors();
}

test('booking twice with the same email lands on one customer', function () {
    bookAs('Anna Ferrari', 'anna@example.test', 0);
    bookAs('Anna Ferrari', 'ANNA@example.test', 1);

    $customer = Customer::query()->sole();

    expect($customer->email)->toBe('anna@example.test')
        ->and($customer->bookings()->count())->toBe(2)
        ->and(Booking::query()->whereNull('customer_id')->count())->toBe(0);
});

test('the directory is searched by name, email or phone', function () {
    Customer::factory()->for($this->tenant)->create(['name' => 'Anna Ferrari', 'email' => 'anna@example.test', 'phone' => '333 111']);
    Customer::factory()->for($this->tenant)->create(['name' => 'Bruno Neri', 'email' => 'bruno@example.test', 'phone' => '333 222']);

    $this->actingAs($this->manager);

    foreach (['ferr', 'anna@', '111'] as $term) {
        $this->get(route('app.customers', ['q' => $term]))->assertInertia(fn (Assert $page) => $page
            ->component('app/customers')
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Anna Ferrari'));
    }
});

test('the freshest booking updates name and phone, never the internal note', function () {
    bookAs('Anna Ferrari', 'anna@example.test', 0);

    Customer::query()->sole()->update(['notes' => 'Paga sempre in contanti']);

    $slot = (new SlotFinder($this->service, $this->operator))->slotsForDay(CarbonImmutable::now($this->tenant->timezone)->addDay()->startOfDay())[1];

    $this->post(route('portal.service.book', [$this->tenant, $this->service]), bookingForm($slot, [
        'name' => 'Anna Ferrari Bianchi',
        'email' => 'anna@example.test',
        'phone' => '+39 333 111222',
    ]))->assertSessionHasNoErrors();

    $customer = Customer::query()->sole();

    expect($customer->name)->toBe('Anna Ferrari Bianchi')
        ->and($customer->phone)->toBe('+39 333 111222')
        ->and($customer->notes)->toBe('Paga sempre in contanti');
});

test('the same email in another portal is another customer', function () {
    bookAs('Anna Ferrari', 'anna@example.test');

    $other = Tenant::factory()->create();
    Customer::factory()->for($other)->create(['email' => 'anna@example.test']);

    expect(Customer::query()->where('email', 'anna@example.test')->count())->toBe(2);
});

test('a manager reads the directory with counts and history', function () {
    bookAs('Anna Ferrari', 'anna@example.test', 0);

    Booking::factory()
        ->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->subDays(10)->setTime(10, 0))
        ->create([
            'customer_id' => Customer::query()->sole()->id,
            'customer_name' => 'Anna Ferrari',
            'customer_email' => 'anna@example.test',
            'status' => BookingStatus::NoShow,
        ]);

    $customer = Customer::query()->sole();

    $this->actingAs($this->manager)
        ->get(route('app.customers', ['customer' => $customer->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('customers.data.0.name', 'Anna Ferrari')
            ->where('customers.data.0.email', 'anna@example.test')
            ->where('customers.data.0.bookings_count', 2)
            ->where('customers.data.0.upcoming_count', 1)
            ->where('customers.data.0.no_show_count', 1)
            ->where('card.id', $customer->id)
            ->has('card.history', 2)
            ->where('card.history.0.service', $this->service->name)
            ->where('card.history.1.status', 'No show'));
});

test('a manager writes an internal note on a customer', function () {
    bookAs('Anna Ferrari', 'anna@example.test');

    $customer = Customer::query()->sole();

    $this->actingAs($this->manager)
        ->patch(route('app.customers.notes', $customer), ['notes' => 'Preferisce il pomeriggio'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Note saved.');

    expect($customer->fresh()->notes)->toBe('Preferisce il pomeriggio');
});

test('customers of another portal are out of reach', function () {
    $other = Tenant::factory()->create();
    $stranger = Customer::factory()->for($other)->create(['notes' => null]);

    $this->actingAs($this->manager);

    $this->get(route('app.customers', ['customer' => $stranger->id]))->assertForbidden();
    $this->patch(route('app.customers.notes', $stranger), ['notes' => 'Scritto da fuori'])->assertForbidden();

    expect($stranger->fresh()->notes)->toBeNull();
});

test('an operator has no directory at all', function () {
    $this->actingAs($this->operator)->get(route('app.customers'))->assertForbidden();
});

test('erasing a customer removes the anagraphic together with the bookings', function () {
    bookAs('Anna Ferrari', 'anna@example.test');

    $booking = Booking::query()->sole();
    $booking->update(['starts_at' => now()->subDays(5), 'ends_at' => now()->subDays(5)->addMinutes(30), 'status' => BookingStatus::Completed]);

    $this->actingAs($this->manager)
        ->post(route('app.bookings.anonymise', $booking))
        ->assertSessionHasNoErrors();

    expect(Customer::query()->count())->toBe(0)
        ->and($booking->fresh()->isAnonymised())->toBeTrue()
        ->and($booking->fresh()->customer_id)->toBeNull();
});
