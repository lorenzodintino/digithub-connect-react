<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GroupSlotFinder;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();

    $this->yoga = Service::factory()->for($this->tenant)->create([
        'name' => 'Yoga',
        'capacity' => 3,
        'duration_minutes' => 60,
        'slot_interval_minutes' => 60,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'cancellation_notice_hours' => 0,
    ]);

    $this->anna = User::factory()->operator($this->tenant)->create(['name' => 'Anna']);
    $this->bruno = User::factory()->operator($this->tenant)->create(['name' => 'Bruno']);

    $this->yoga->operators()->attach([$this->anna->id, $this->bruno->id]);

    foreach ([$this->anna, $this->bruno] as $teacher) {
        foreach (range(0, 6) as $weekday) {
            $teacher->availabilityRules()->create([
                'weekday' => $weekday,
                'starts_at' => '09:00:00',
                'ends_at' => '12:00:00',
            ]);
        }
    }

    $this->day = CarbonImmutable::now($this->tenant->timezone)->addDay()->startOfDay();
});

/**
 * Book a seat on the class through the public page.
 */
function bookSeat(string $name, CarbonImmutable $slot): void
{
    Livewire::test('pages::portal.service', ['tenant' => test()->tenant, 'service' => test()->yoga])
        ->call('selectSlot', $slot->toIso8601String())
        ->set('customerName', $name)
        ->set('customerEmail', str($name)->slug()->value().'@example.test')
        ->set('privacyAccepted', true)
        ->call('book')
        ->assertHasNoErrors();
}

test('the first booking opens the session with everybody who is free', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    bookSeat('Anna Ferrari', $slot);

    $session = ServiceSession::query()->sole();

    expect($session->capacity)->toBe(3)
        ->and($session->operators->pluck('name')->sort()->values()->all())->toBe(['Anna', 'Bruno'])
        ->and($session->seatsTaken())->toBe(1)
        ->and(Booking::query()->sole()->service_session_id)->toBe($session->id);
});

test('more customers take the seats of the same session', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    bookSeat('Prima Cliente', $slot);
    bookSeat('Seconda Cliente', $slot);

    expect(ServiceSession::query()->count())->toBe(1)
        ->and(ServiceSession::query()->sole()->seatsTaken())->toBe(2)
        ->and(Booking::query()->count())->toBe(2);
});

test('a full session takes its time off the list', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    foreach (['Prima', 'Seconda', 'Terza'] as $name) {
        bookSeat($name, $slot);
    }

    $finder = new GroupSlotFinder($this->yoga);

    expect(ServiceSession::query()->sole()->isFull())->toBeTrue()
        ->and($finder->canBookAt($slot))->toBeFalse()
        ->and(collect($finder->slotsForDay($this->day))->contains(fn ($free) => $free->equalTo($slot)))->toBeFalse();
});

test('a cancelled seat is offered again', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    foreach (['Prima', 'Seconda', 'Terza'] as $name) {
        bookSeat($name, $slot);
    }

    Booking::query()->first()->update(['status' => BookingStatus::Cancelled]);

    expect(ServiceSession::query()->sole()->seatsLeft())->toBe(1)
        ->and((new GroupSlotFinder($this->yoga))->canBookAt($slot))->toBeTrue();
});

test('a session takes the time of every teacher giving it', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    $oneToOne = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 60,
        'slot_interval_minutes' => 60,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
    ]);

    $oneToOne->operators()->attach([$this->anna->id, $this->bruno->id]);

    bookSeat('Prima Cliente', $slot);

    foreach ([$this->anna, $this->bruno] as $teacher) {
        $free = (new SlotFinder($oneToOne, $teacher))->canBookAt($slot);

        expect($free)->toBeFalse();
    }
});

test('a class with nobody free anymore is not offered', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    // Both teachers are taken elsewhere at that hour.
    $other = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 60,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
    ]);

    foreach ([$this->anna, $this->bruno] as $teacher) {
        Booking::factory()->forSlot($other, $teacher, $slot)->create();
    }

    expect((new GroupSlotFinder($this->yoga))->canBookAt($slot))->toBeFalse();
});

test('one teacher free is enough to open the class', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    $other = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 60,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
    ]);

    Booking::factory()->forSlot($other, $this->bruno, $slot)->create();

    bookSeat('Prima Cliente', $slot);

    expect(ServiceSession::query()->sole()->operators->pluck('name')->all())->toBe(['Anna']);
});

test('a seat cannot be moved to another hour', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    bookSeat('Prima Cliente', $slot);

    $booking = Booking::query()->sole();

    Livewire::test('pages::portal.booking', ['tenant' => $this->tenant, 'booking' => $booking])
        ->call('startReschedule')
        ->assertStatus(422);
});

test('a manager sets the places from the service form', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('editService', $this->yoga->slug)
        ->assertSet('form.capacity', 3)
        ->set('form.capacity', 8)
        ->call('saveService')
        ->assertHasNoErrors();

    expect($this->yoga->fresh()->capacity)->toBe(8);
});

test('a one to one service is untouched by any of this', function () {
    $single = Service::factory()->for($this->tenant)->create([
        'capacity' => 1,
        'duration_minutes' => 60,
        'slot_interval_minutes' => 60,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
    ]);

    $single->operators()->attach($this->anna);

    $slot = (new SlotFinder($single, $this->anna))->slotsForDay($this->day)[0];

    Booking::factory()->forSlot($single, $this->anna, $slot)->create();

    expect($single->isGroup())->toBeFalse()
        ->and(ServiceSession::query()->count())->toBe(0)
        ->and((new SlotFinder($single, $this->anna))->canBookAt($slot))->toBeFalse();
});

test('a manager adds and removes teachers on a session', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    $other = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 60,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
    ]);

    // Bruno is taken elsewhere, so the class opens with Anna alone.
    $elsewhere = Booking::factory()->forSlot($other, $this->bruno, $slot)->create();

    bookSeat('Prima Cliente', $slot);

    $booking = Booking::query()->where('service_id', $this->yoga->id)->sole();
    $session = ServiceSession::query()->sole();

    $component = Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('showBooking', $booking->id);

    // Bruno is busy: he is not on offer, and forcing it is refused.
    expect($component->get('assignableToSession'))->toHaveCount(0);

    $component->call('addSessionOperator', $this->bruno->id)->assertStatus(422);

    // Once he is free, he can join.
    $elsewhere->update(['status' => BookingStatus::Cancelled]);

    $component = Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('showBooking', $booking->id);

    expect($component->get('assignableToSession')->pluck('name')->all())->toBe(['Bruno']);

    $component->call('addSessionOperator', $this->bruno->id);

    expect($session->fresh()->operators()->count())->toBe(2);

    $component->call('removeSessionOperator', $this->anna->id);

    expect($session->fresh()->operators->pluck('name')->all())->toBe(['Bruno'])
        // The booking followed the teacher who stayed.
        ->and($booking->fresh()->user_id)->toBe($this->bruno->id);
});

test('the last teacher cannot be taken off a session', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    bookSeat('Prima Cliente', $slot);

    $booking = Booking::query()->sole();
    $session = ServiceSession::query()->sole();

    $component = Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('showBooking', $booking->id);

    foreach ($session->operators as $teacher) {
        $component->call('removeSessionOperator', $teacher->id);
    }

    expect($session->fresh()->operators()->count())->toBe(1);
});

test('an operator cannot rearrange the teachers of a session', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    bookSeat('Prima Cliente', $slot);

    $booking = Booking::query()->sole();

    Livewire::actingAs($this->anna)
        ->test('pages::app.bookings')
        ->call('showBooking', $booking->id)
        ->call('removeSessionOperator', $this->bruno->id)
        ->assertForbidden();
});

test('a time running out of seats says so, and a roomy one says how many are free', function () {
    $slots = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day);

    bookSeat('Prima Cliente', $slots[0]);
    bookSeat('Seconda Cliente', $slots[0]);

    Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->yoga])
        ->call('selectDate', $this->day->format('Y-m-d'))
        // Singular, and it says to hurry rather than reporting a number.
        ->assertSee('last seat left')
        ->assertDontSee('1 seats')
        // The hour nobody booked still reads as what it is: an empty room.
        ->assertSee('3 seats free');
});

test('a day is marked nearly full only when every time on it is short', function () {
    $slots = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day);

    // One hour left with a single seat, the others untouched.
    bookSeat('Prima Cliente', $slots[0]);
    bookSeat('Seconda Cliente', $slots[0]);

    $page = fn () => Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->yoga]);

    $page()->assertDontSee('A day marked in amber is nearly full.');

    // Now every hour of the day is down to its last seat. The public form
    // allows five bookings a minute, and filling a day takes more.
    foreach (array_slice($slots, 1) as $index => $slot) {
        RateLimiter::clear('booking:'.$this->yoga->id.':127.0.0.1:minute');

        bookSeat("Terza {$index}", $slot);
        bookSeat("Quarta {$index}", $slot);
    }

    $page()->assertSee('A day marked in amber is nearly full.');
});

test('a session everybody cancelled out of offers the whole room again', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    bookSeat('Prima Cliente', $slot);
    bookSeat('Seconda Cliente', $slot);

    Booking::query()->update(['status' => BookingStatus::Cancelled]);

    $finder = new GroupSlotFinder($this->yoga);
    $seats = $finder->seatsLeftBetween($this->day, $this->day);

    expect($seats)->toBe([])
        ->and($finder->seatsLeftAt($slot))->toBe(3);
});

test('the seat counts of a whole month are read in one query', function () {
    $slots = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day);

    bookSeat('Prima Cliente', $slots[0]);
    bookSeat('Seconda Cliente', $slots[1]);

    $seats = (new GroupSlotFinder($this->yoga))->seatsLeftBetween($this->day, $this->day->addDays(30));

    expect($seats)->toBe([
        $slots[0]->getTimestamp() => 2,
        $slots[1]->getTimestamp() => 2,
    ]);
});

test('few seats is a quarter of the room, at least one', function () {
    $eight = Service::factory()->for($this->tenant)->create(['capacity' => 8]);
    $two = Service::factory()->for($this->tenant)->create(['capacity' => 2]);

    expect($eight->seatsRunningLow(2))->toBeTrue()
        ->and($eight->seatsRunningLow(3))->toBeFalse()
        // A room of two is short at one, and "none left" is not a warning:
        // that time is not offered at all.
        ->and($two->seatsRunningLow(1))->toBeTrue()
        ->and($two->seatsRunningLow(0))->toBeFalse();
});
