<?php

use App\Actions\Bookings\CreateBooking;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);

    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'requires_confirmation' => false,
    ]);

    $this->anna = User::factory()->operator($this->tenant)->create(['name' => 'Anna']);
    $this->bruno = User::factory()->operator($this->tenant)->create(['name' => 'Bruno']);

    $this->service->operators()->attach([$this->anna->id, $this->bruno->id]);
});

/**
 * Give a person working hours on the given weekdays.
 *
 * @param  array<int, int>  $weekdays
 */
function worksFrom(User $person, string $from, string $to, array $weekdays = [0, 1, 2, 3, 4, 5, 6]): void
{
    foreach ($weekdays as $weekday) {
        $person->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => $from.':00',
            'ends_at' => $to.':00',
        ]);
    }
}

/**
 * Tomorrow at a given local hour, as a UTC instant.
 */
function slotAt(int $hour): CarbonImmutable
{
    return CarbonImmutable::now('Europe/Rome')->addDay()->startOfDay()->setTime($hour, 0)->setTimezone('UTC');
}

/**
 * Tomorrow as a date in the portal timezone.
 */
function tomorrowDate(): string
{
    return CarbonImmutable::now('Europe/Rome')->addDay()->format('Y-m-d');
}

/**
 * The customer payload used across these tests.
 *
 * @return array<string, string>
 */
function anyCustomer(array $overrides = []): array
{
    return [
        'name' => 'Mario',
        'email' => 'mario@example.com',
        'timezone' => 'Europe/Rome',
        'locale' => 'it',
        ...$overrides,
    ];
}

test('the merged calendar offers a slot as long as somebody can take it', function () {
    worksFrom($this->anna, '09:00', '10:00');
    worksFrom($this->bruno, '15:00', '16:00');

    // Neither covers both halves of the day; together they do.
    Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->service])
        ->assertSet('operatorId', null)
        ->call('selectDate', tomorrowDate())
        ->assertSee('09:00')
        ->assertSee('15:00');
});

test('picking one person narrows the calendar back down', function () {
    worksFrom($this->anna, '09:00', '10:00');
    worksFrom($this->bruno, '15:00', '16:00');

    Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->service])
        ->call('selectOperator', $this->anna->id)
        ->call('selectDate', tomorrowDate())
        ->assertSee('09:00')
        ->assertDontSee('15:00');
});

test('a service with one person alone skips the choice entirely', function () {
    $this->service->operators()->detach($this->bruno);
    worksFrom($this->anna, '09:00', '10:00');

    Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->service->fresh()])
        ->assertSet('operatorId', $this->anna->id)
        ->assertDontSee('Anyone available');
});

test('leaving the choice open assigns the least busy person', function () {
    worksFrom($this->anna, '09:00', '12:00');
    worksFrom($this->bruno, '09:00', '12:00');

    // Anna already has two appointments coming up, Bruno none.
    Booking::factory()->forSlot($this->service, $this->anna, slotAt(10))->create();
    Booking::factory()->forSlot($this->service, $this->anna, slotAt(11))->create();

    $booking = app(CreateBooking::class)->handle($this->service, null, slotAt(9), anyCustomer());

    expect($booking->user_id)->toBe($this->bruno->id);
});

test('a busy first choice hands the booking to the next person', function () {
    worksFrom($this->anna, '09:00', '10:00');
    worksFrom($this->bruno, '09:00', '10:00');

    // Anna sorts first on workload but is taken at nine.
    Booking::factory()->forSlot($this->service, $this->anna, slotAt(9))->create();

    $booking = app(CreateBooking::class)->handle($this->service, null, slotAt(9), anyCustomer());

    expect($booking->user_id)->toBe($this->bruno->id);
});

test('with everybody taken the slot is refused', function () {
    worksFrom($this->anna, '09:00', '10:00');
    worksFrom($this->bruno, '09:00', '10:00');

    Booking::factory()->forSlot($this->service, $this->anna, slotAt(9))->create();
    Booking::factory()->forSlot($this->service, $this->bruno, slotAt(9))->create();

    app(CreateBooking::class)->handle($this->service, null, slotAt(9), anyCustomer());
})->throws(SlotUnavailableException::class);

test('somebody who is not bookable is never assigned', function () {
    worksFrom($this->anna, '09:00', '10:00');
    worksFrom($this->bruno, '09:00', '10:00');
    $this->anna->update(['is_bookable' => false]);

    $booking = app(CreateBooking::class)->handle($this->service, null, slotAt(9), anyCustomer());

    expect($booking->user_id)->toBe($this->bruno->id);
});

test('booking without a choice works end to end from the page', function () {
    worksFrom($this->anna, '09:00', '10:00');
    worksFrom($this->bruno, '09:00', '10:00');

    Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->service])
        ->call('selectSlot', slotAt(9)->toIso8601String())
        ->set('customerName', 'Mario Bianchi')
        ->set('customerEmail', 'mario@example.com')
        ->set('privacyAccepted', true)
        ->call('book')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Booking::query()->sole()->user_id)->toBeIn([$this->anna->id, $this->bruno->id]);
});

test('choosing a person explicitly still books that person', function () {
    worksFrom($this->anna, '09:00', '10:00');
    worksFrom($this->bruno, '09:00', '10:00');

    $booking = app(CreateBooking::class)->handle($this->service, $this->bruno, slotAt(9), anyCustomer());

    expect($booking->user_id)->toBe($this->bruno->id);
});

test('a day nobody works stays empty in the merged calendar', function () {
    // Anna on Mondays, Bruno on Tuesdays, nobody on Wednesday.
    worksFrom($this->anna, '09:00', '10:00', [1]);
    worksFrom($this->bruno, '09:00', '10:00', [2]);

    $wednesday = CarbonImmutable::now('Europe/Rome')->addDay()->startOfDay();

    while ($wednesday->dayOfWeek !== 3) {
        $wednesday = $wednesday->addDay();
    }

    $component = Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->service])
        ->call('selectDate', $wednesday->format('Y-m-d'));

    expect($component->instance()->daySlots)->toBe([]);
});
