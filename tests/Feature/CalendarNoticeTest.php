<?php

use App\Models\Booking;
use App\Models\PortalClosure;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);

    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'max_advance_days' => 365,
    ]);

    $this->operator = User::factory()->operator($this->tenant)->create();
    $this->service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '17:00:00',
        ]);
    }

    $this->today = CarbonImmutable::now($this->tenant->timezone)->startOfDay();
});

/**
 * The public page of the service under test.
 */
function servicePage(): TestResponse
{
    return test()->get(route('portal.service', [test()->tenant, test()->service]));
}

/**
 * The lines under the calendar that explain the days nobody can pick.
 */
function calendarNotes(?TestResponse $page = null, string $calendar = 'calendar'): string
{
    return implode("\n", ($page ?? servicePage())->inertiaProps($calendar.'.notes'));
}

test('the calendar says when the booking window runs out', function () {
    $this->service->update(['max_advance_days' => 1]);

    $latest = $this->today->addDay();

    expect(calendarNotes())->toContain('You can book up to '.$latest->translatedFormat('j F'));
});

test('a window wider than the calendar is not mentioned', function () {
    expect(calendarNotes())->not->toContain('You can book up to');
});

test('the calendar says which days the portal is shut', function () {
    $first = $this->today->startOfMonth()->addDays(9);
    $last = $first->addDays(4);

    PortalClosure::create([
        'tenant_id' => $this->tenant->id,
        'starts_on' => $first->toDateString(),
        'ends_on' => $last->toDateString(),
        'reason' => 'Bereavement in the family',
    ]);

    $page = servicePage();

    expect(calendarNotes($page))->toContain('Closed from '.$first->translatedFormat('j').' to '.$last->translatedFormat('j F'));

    // What the manager wrote is for their own agenda, not for visitors.
    $page->assertDontSee('Bereavement in the family');
});

test('a closure of a single day is worded as one day', function () {
    $day = $this->today->startOfMonth()->addDays(9);

    PortalClosure::create([
        'tenant_id' => $this->tenant->id,
        'starts_on' => $day->toDateString(),
        'ends_on' => $day->toDateString(),
        'reason' => null,
    ]);

    expect(calendarNotes())->toContain('Closed on '.$day->translatedFormat('j F'))
        ->not->toContain('Closed from');
});

test('a closure straddling two months names both', function () {
    // startOfMonth before addMonth: on the 31st the month arithmetic overflows.
    $last = $this->today->startOfMonth()->addMonth()->addDay();
    $first = $last->subDays(3);

    PortalClosure::create([
        'tenant_id' => $this->tenant->id,
        'starts_on' => $first->toDateString(),
        'ends_on' => $last->toDateString(),
        'reason' => null,
    ]);

    expect(calendarNotes())->toContain(
        'Closed from '.$first->translatedFormat('j F').' to '.$last->translatedFormat('j F')
    );
});

test('the reschedule calendar explains its days too', function () {
    // The slot is picked while the window is still wide: shrinking it first
    // would take that day off the list altogether.
    $slot = (new SlotFinder($this->service, $this->operator))->slotsForDay($this->today->addDays(10))[0];

    $booking = Booking::factory()->forSlot($this->service, $this->operator, $slot)->create();

    $this->service->update(['max_advance_days' => 1, 'cancellation_notice_hours' => 0]);

    $page = $this->get(route('portal.booking', [$this->tenant, $booking, 'reschedule' => 1]));

    expect(calendarNotes($page, 'reschedule.calendar'))->toContain('You can book up to '.$this->today->addDay()->translatedFormat('j F'));
});
