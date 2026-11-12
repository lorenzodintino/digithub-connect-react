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
use Inertia\Testing\AssertableInertia as Assert;

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
    test()->post(route('portal.service.book', [test()->tenant, test()->yoga]), bookingForm($slot, [
        'name' => $name,
        'email' => str($name)->slug()->value().'@example.test',
    ]))->assertSessionHasNoErrors();
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

    $this->get(route('portal.booking', [$this->tenant, $booking, 'reschedule' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('booking.can.reschedule', false)->where('reschedule', null));

    $this->post(route('portal.booking.reschedule', [$this->tenant, $booking]), ['slot' => $slot->addHour()->toIso8601String()])
        ->assertStatus(422);
});

test('a manager sets the places from the service form', function () {
    expect(serviceForm($this->yoga)['capacity'])->toBe(3);

    $this->actingAs($this->manager)
        ->put(route('app.services.update', $this->yoga->slug), serviceForm($this->yoga, ['capacity' => 8]))
        ->assertSessionHasNoErrors();

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

    $this->actingAs($this->manager);

    // Bruno is busy: he is not on offer, and forcing it is refused.
    $this->get(route('app.bookings', ['booking' => $booking->uid]))
        ->assertInertia(fn (Assert $page) => $page->where('details.session.candidates', []));

    $this->post(route('app.bookings.session.store', $booking), ['user' => $this->bruno->id])->assertStatus(422);

    // Once he is free, he can join.
    $elsewhere->update(['status' => BookingStatus::Cancelled]);

    $this->get(route('app.bookings', ['booking' => $booking->uid]))
        ->assertInertia(fn (Assert $page) => $page->where('details.session.candidates', [['id' => $this->bruno->id, 'name' => 'Bruno']]));

    $this->post(route('app.bookings.session.store', $booking), ['user' => $this->bruno->id])->assertSessionHasNoErrors();

    expect($session->fresh()->operators()->count())->toBe(2);

    $this->delete(route('app.bookings.session.destroy', [$booking, $this->anna]))->assertSessionHasNoErrors();

    expect($session->fresh()->operators->pluck('name')->all())->toBe(['Bruno'])
        // The booking followed the teacher who stayed.
        ->and($booking->fresh()->user_id)->toBe($this->bruno->id);
});

test('the last teacher cannot be taken off a session', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    bookSeat('Prima Cliente', $slot);

    $booking = Booking::query()->sole();
    $session = ServiceSession::query()->sole();

    $this->actingAs($this->manager);

    $this->delete(route('app.bookings.session.destroy', [$booking, $this->anna]))->assertSessionHasNoErrors();
    $this->delete(route('app.bookings.session.destroy', [$booking, $this->bruno]))
        ->assertSessionHasErrors(['session' => 'Somebody has to give this session.']);

    expect($session->fresh()->operators()->count())->toBe(1);
});

test('a teacher of another portal cannot be put on a session', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    bookSeat('Prima Cliente', $slot);

    $booking = Booking::query()->sole();
    $stranger = User::factory()->operator(Tenant::factory()->create())->create();

    $this->actingAs($this->manager)
        ->post(route('app.bookings.session.store', $booking), ['user' => $stranger->id])
        ->assertForbidden();

    expect(ServiceSession::query()->sole()->operators()->whereKey($stranger->id)->exists())->toBeFalse();
});

test('session places are readable in the selected language', function (string $locale, int $seats, string $message) {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    foreach (range(1, $seats) as $seat) {
        bookSeat('Cliente '.$seat, $slot);
    }

    $booking = Booking::query()->firstOrFail();
    $this->manager->update(['locale' => $locale]);
    app()->setLocale($locale);

    $this->actingAs($this->manager)
        ->get(route('app.bookings', ['booking' => $booking->uid]))
        ->assertInertia(fn (Assert $page) => $page->where('details.session.seats', $message));
})->with([
    'Italian singular' => ['it', 1, 'un posto su 3'],
    'Italian plural' => ['it', 2, '2 posti su 3'],
    'English singular' => ['en', 1, 'one of 3 places'],
    'English plural' => ['en', 2, '2 of 3 places'],
]);

test('an operator cannot rearrange the teachers of a session', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];

    bookSeat('Prima Cliente', $slot);

    $booking = Booking::query()->sole();

    $this->actingAs($this->anna)
        ->get(route('app.bookings', ['booking' => $booking->uid]))
        ->assertInertia(fn (Assert $page) => $page->where('details.session.can_manage', false)->where('details.session.candidates', []));

    $this->delete(route('app.bookings.session.destroy', [$booking, $this->bruno]))->assertForbidden();
});

test('a time running out of seats says so, and a roomy one says how many are free', function () {
    $slots = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day);

    bookSeat('Prima Cliente', $slots[0]);
    bookSeat('Seconda Cliente', $slots[0]);

    $seats = collect($this->get(route('portal.service', [$this->tenant, $this->yoga, 'd' => $this->day->format('Y-m-d')]))->inertiaProps('day.slots'))->pluck('seats');

    // Singular, and it says to hurry rather than reporting a number.
    expect($seats[0])->toBe(['label' => 'last seat left', 'low' => true])
        // The hour nobody booked still reads as what it is: an empty room.
        ->and($seats[1])->toBe(['label' => '3 seats free', 'low' => false]);
});

test('a day is marked nearly full only when every time on it is short', function () {
    $slots = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day);

    // One hour left with a single seat, the others untouched.
    bookSeat('Prima Cliente', $slots[0]);
    bookSeat('Seconda Cliente', $slots[0]);

    $page = fn () => $this->get(route('portal.service', [$this->tenant, $this->yoga, 'month' => $this->day->format('Y-m')]));
    $date = $this->day->format('Y-m-d');

    $page()->assertInertia(fn (Assert $page) => $page
        ->where('calendar.nearly_full', false)
        ->where('calendar.days', fn ($days) => collect($days)->firstWhere('date', $date)['seats_left'] === null));

    // Now every hour of the day is down to its last seat. The public form
    // allows five bookings a minute, and filling a day takes more.
    foreach (array_slice($slots, 1) as $index => $slot) {
        RateLimiter::clear('booking:'.$this->yoga->id.':127.0.0.1:minute');

        bookSeat("Terza {$index}", $slot);
        bookSeat("Quarta {$index}", $slot);
    }

    $page()->assertInertia(fn (Assert $page) => $page
        ->where('calendar.nearly_full', true)
        ->where('calendar.days', fn ($days) => collect($days)->firstWhere('date', $date)['seats_left'] === 1));
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

test('the week grid draws a class as one block with its participants', function () {
    $slot = (new GroupSlotFinder($this->yoga))->slotsForDay($this->day)[0];
    bookSeat('Anna Ferrari', $slot);
    bookSeat('Bruno Neri', $slot);
    bookSeat('Carla Verdi', $slot);

    $this->actingAs($this->manager)
        ->get(route('app.bookings', ['view' => 'week', 'd' => $this->day->format('Y-m-d')]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('calendar.grid.days.'.$this->day->format('Y-m-d'), 1)
            ->where('calendar.grid.days.'.$this->day->format('Y-m-d').'.0.session', true)
            ->where('calendar.grid.days.'.$this->day->format('Y-m-d').'.0.label', '3 participants')
            ->where('calendar.grid.days.'.$this->day->format('Y-m-d').'.0.lanes', 1));
});
