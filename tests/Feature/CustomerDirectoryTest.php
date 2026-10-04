<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

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

    Livewire::test('pages::portal.service', ['tenant' => test()->tenant, 'service' => test()->service])
        ->call('selectSlot', $slot->toIso8601String())
        ->set('customerName', $name)
        ->set('customerEmail', $email)
        ->set('privacyAccepted', true)
        ->call('book')
        ->assertHasNoErrors();
}

test('booking twice with the same email lands on one customer', function () {
    bookAs('Anna Ferrari', 'anna@example.test', 0);
    bookAs('Anna Ferrari', 'ANNA@example.test', 1);

    $customer = Customer::query()->sole();

    expect($customer->email)->toBe('anna@example.test')
        ->and($customer->bookings()->count())->toBe(2)
        ->and(Booking::query()->whereNull('customer_id')->count())->toBe(0);
});

test('customer search has a visible label', function () {
    $this->actingAs($this->manager)
        ->get(route('app.customers'))
        ->assertSee('label="Search"', escape: false)
        ->assertSee('data-flux-label', escape: false);
});

test('search clearing labels follow language changes', function () {
    $this->actingAs($this->manager);

    foreach (['en' => 'Clear input', 'it' => 'Svuota il campo'] as $locale => $label) {
        $this->get(route('app.customers').'?lang='.$locale)
            ->assertSee('aria-label="'.$label.'"', escape: false);
    }
});

test('the freshest booking updates name and phone, never the internal note', function () {
    bookAs('Anna Ferrari', 'anna@example.test', 0);

    Customer::query()->sole()->update(['notes' => 'Paga sempre in contanti']);

    Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->service])
        ->call('selectSlot', (new SlotFinder($this->service, $this->operator))
            ->slotsForDay(CarbonImmutable::now($this->tenant->timezone)->addDay()->startOfDay())[1]->toIso8601String())
        ->set('customerName', 'Anna Ferrari Bianchi')
        ->set('customerEmail', 'anna@example.test')
        ->set('customerPhone', '+39 333 111222')
        ->set('privacyAccepted', true)
        ->call('book')
        ->assertHasNoErrors();

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

    Livewire::actingAs($this->manager)
        ->test('pages::app.customers')
        ->assertSee('Anna Ferrari')
        ->assertSee('anna@example.test')
        ->call('showCustomer', $customer->id)
        ->assertSet('openId', $customer->id)
        ->assertSee($this->service->name);
});

test('a manager writes an internal note on a customer', function () {
    bookAs('Anna Ferrari', 'anna@example.test');

    $customer = Customer::query()->sole();

    Livewire::actingAs($this->manager)
        ->test('pages::app.customers')
        ->call('showCustomer', $customer->id)
        ->set('notes', 'Preferisce il pomeriggio')
        ->call('saveNotes')
        ->assertHasNoErrors();

    expect($customer->fresh()->notes)->toBe('Preferisce il pomeriggio');
});

test('customers of another portal are out of reach', function () {
    $other = Tenant::factory()->create();
    $stranger = Customer::factory()->for($other)->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.customers')
        ->call('showCustomer', $stranger->id)
        ->assertForbidden();
});

test('an operator has no directory at all', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::app.customers')
        ->assertForbidden();

    $this->actingAs($this->operator)->get(route('app.customers'))->assertForbidden();
});

test('erasing a customer removes the anagraphic together with the bookings', function () {
    bookAs('Anna Ferrari', 'anna@example.test');

    $booking = Booking::query()->sole();
    $booking->update(['starts_at' => now()->subDays(5), 'ends_at' => now()->subDays(5)->addMinutes(30), 'status' => BookingStatus::Completed]);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('askToAnonymise', $booking->id)
        ->call('anonymiseCustomer');

    expect(Customer::query()->count())->toBe(0)
        ->and($booking->fresh()->isAnonymised())->toBeTrue()
        ->and($booking->fresh()->customer_id)->toBeNull();
});
