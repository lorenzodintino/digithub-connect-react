<?php

use App\Actions\Bookings\CreateBooking;
use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Services\GroupSlotFinder;
use App\Services\Impersonation;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();

    $this->yoga = Service::factory()->for($this->tenant)->create([
        'name' => 'Yoga',
        'capacity' => 5,
        'duration_minutes' => 60,
        'slot_interval_minutes' => 60,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'max_advance_days' => 60,
        'cancellation_notice_hours' => 0,
    ]);

    $this->yoga->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '18:00:00',
        ]);
    }

    $this->slot = (new GroupSlotFinder($this->yoga))
        ->slotsForDay(CarbonImmutable::now($this->tenant->timezone)->addDay()->startOfDay())[0];
});

/**
 * Open the session of the group class at the given hour.
 */
function openSession(CarbonImmutable $slot): ServiceSession
{
    app(CreateBooking::class)->handle(test()->yoga, null, $slot, [
        'name' => 'Prima Cliente',
        'email' => 'prima@example.test',
    ]);

    return ServiceSession::query()->sole();
}

test('a seat on a class respects the minimum notice like any other booking', function () {
    openSession($this->slot);

    // The portal now wants two days of warning; the class is tomorrow.
    $this->yoga->update(['min_notice_minutes' => 60 * 48]);

    expect((new GroupSlotFinder($this->yoga))->canBookAt($this->slot))->toBeFalse();

    expect(fn () => app(CreateBooking::class)->handle($this->yoga, null, $this->slot, [
        'name' => 'Seconda Cliente',
        'email' => 'seconda@example.test',
    ]))->toThrow(SlotUnavailableException::class);

    expect(Booking::query()->count())->toBe(1);
});

test('a seat cannot be taken on a class that already happened', function () {
    $past = CarbonImmutable::now('UTC')->subDays(2)->setTime(10, 0);

    $session = ServiceSession::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $this->yoga->id,
        'starts_at' => $past,
        'ends_at' => $past->addHour(),
        'blocked_starts_at' => $past,
        'blocked_ends_at' => $past->addHour(),
        'capacity' => 5,
    ]);

    $session->operators()->attach($this->operator);

    expect(fn () => app(CreateBooking::class)->handle($this->yoga, null, $past, [
        'name' => 'Cliente furbo',
        'email' => 'furbo@example.test',
    ]))->toThrow(SlotUnavailableException::class);
});

test('a class on a day the portal is closed is not on offer', function () {
    openSession($this->slot);

    $this->tenant->closures()->create([
        'starts_on' => $this->slot->setTimezone($this->tenant->timezone)->toDateString(),
        'ends_on' => $this->slot->setTimezone($this->tenant->timezone)->toDateString(),
        'reason' => 'Ferie',
    ]);

    expect((new GroupSlotFinder($this->yoga))->canBookAt($this->slot))->toBeFalse();
});

test('an export cannot smuggle a formula into a spreadsheet', function () {
    $service = Service::factory()->for($this->tenant)->create(['name' => 'Consulenza']);

    Booking::factory()
        ->forSlot($service, $this->operator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create([
            'customer_name' => '=cmd|\' /C calc\'!A0',
            'customer_email' => 'furbo@example.test',
            'status' => BookingStatus::Confirmed,
        ]);

    $component = Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('range', 'all')
        ->call('exportCsv');

    $csv = base64_decode(data_get($component->effects, 'download.content'));

    // Excel treats a leading = + - @ as a formula: the value must arrive quoted
    // as text, not as something the spreadsheet will run.
    expect($csv)->not->toContain(';=cmd')
        ->and($csv)->toContain("'=cmd");
});

test('leaving a waiting list is not something a link scanner can do', function () {
    $entry = WaitingListEntry::factory()->create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $this->yoga->id,
        'customer_locale' => 'en',
    ]);

    // Opening the page must not remove anybody: mail scanners and previews
    // fetch every link in a message.
    $this->get(route('portal.waiting-list.leave', [$this->tenant, $entry]))->assertOk();

    expect(WaitingListEntry::query()->count())->toBe(1);

    Livewire::test('pages::portal.waiting-list', ['tenant' => $this->tenant, 'entry' => $entry])
        ->call('leave');

    expect(WaitingListEntry::query()->count())->toBe(0);
});

test('a manager cannot read the agenda of another portal through the calendar', function () {
    $stranger = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $strangerOperator = User::factory()->operator($stranger)->create();
    $strangerService = Service::factory()->for($stranger)->create();

    $hidden = Booking::factory()
        ->forSlot($strangerService, $strangerOperator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create(['customer_name' => 'Cliente estraneo']);

    $component = Livewire::actingAs($this->manager)->test('pages::app.bookings');

    $component->set('view', 'month')->assertDontSee('Cliente estraneo');

    // Nor by pointing straight at the booking id.
    $component->call('showBooking', $hidden->id)->assertForbidden();
});

test('a booking of another portal cannot be erased from here', function () {
    $stranger = Tenant::factory()->create();
    $strangerOperator = User::factory()->operator($stranger)->create();
    $strangerService = Service::factory()->for($stranger)->create();

    $hidden = Booking::factory()
        ->forSlot($strangerService, $strangerOperator, CarbonImmutable::now('UTC')->subDays(3)->setTime(10, 0))
        ->create(['customer_email' => 'estraneo@example.test']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('askToAnonymise', $hidden->id)
        ->assertForbidden();

    expect($hidden->fresh()->isAnonymised())->toBeFalse();
});

test('a waiting list entry of another portal cannot be removed from here', function () {
    $stranger = Tenant::factory()->create();
    $service = Service::factory()->for($stranger)->create();

    $entry = WaitingListEntry::factory()->create([
        'tenant_id' => $stranger->id,
        'service_id' => $service->id,
    ]);

    Livewire::actingAs($this->manager)
        ->test('pages::app.waiting-list')
        ->call('remove', $entry->id);

    expect(WaitingListEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

test('the register of another portal stays out of reach even when filtered by its people', function () {
    $stranger = Tenant::factory()->create();
    $strangerManager = User::factory()->manager($stranger)->create(['name' => 'Manager estraneo']);

    ActivityLog::factory()->create([
        'tenant_id' => $stranger->id,
        'user_id' => $strangerManager->id,
        'actor_name' => 'Manager estraneo',
        'subject_label' => 'Segreto altrui',
    ]);

    Livewire::actingAs($this->manager)
        ->test('pages::shared.activity')
        ->set('actor', (string) $strangerManager->id)
        ->assertDontSee('Segreto altrui');
});

test('a day view cannot be pointed at nonsense', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('showDayView', 'non-una-data')
        ->assertStatus(422);
});

test('the reports of an operator hold only their own work', function () {
    $colleague = User::factory()->operator($this->tenant)->create();
    $service = Service::factory()->for($this->tenant)->create();

    Booking::factory()->forSlot($service, $this->operator, CarbonImmutable::now('UTC')->startOfMonth()->addDays(2)->setTime(10, 0))->create();
    Booking::factory()->forSlot($service, $colleague, CarbonImmutable::now('UTC')->startOfMonth()->addDays(3)->setTime(10, 0))->create();

    $component = Livewire::actingAs($this->operator)->test('pages::app.reports');

    expect($component->get('totals')['bookings'])->toBe(1);
});

test('switching language while standing in for somebody leaves their account alone', function () {
    $admin = User::factory()->admin()->create();

    // The suite runs in English, so the account has to start somewhere else
    // for the assertion to mean anything.
    $this->manager->update(['locale' => 'it']);

    $this->actingAs($admin);

    app(Impersonation::class)->start($admin, $this->manager);

    $this->get(route('dashboard').'?lang=en')->assertOk();

    expect($this->manager->fresh()->locale)->toBe('it');
});

test('the customer export is closed to an operator', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::app.customers')
        ->assertForbidden();

    $this->actingAs($this->operator)->get(route('app.customers'))->assertForbidden();
});

test('an operator exports only their own bookings', function () {
    $colleague = User::factory()->operator($this->tenant)->create();
    $service = Service::factory()->for($this->tenant)->create();

    Booking::factory()->forSlot($service, $this->operator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create(['customer_name' => 'Cliente mio']);

    Booking::factory()->forSlot($service, $colleague, CarbonImmutable::now('UTC')->addDay()->setTime(11, 0))
        ->create(['customer_name' => 'Cliente del collega']);

    $component = Livewire::actingAs($this->operator)
        ->test('pages::app.bookings')
        ->set('range', 'all')
        ->call('exportCsv');

    $csv = base64_decode(data_get($component->effects, 'download.content'));

    expect($csv)->toContain('Cliente mio')->not->toContain('Cliente del collega');
});

test('a seat on a full class is refused even when asked for directly', function () {
    $this->yoga->update(['capacity' => 1]);

    app(CreateBooking::class)->handle($this->yoga, null, $this->slot, [
        'name' => 'Prima Cliente',
        'email' => 'prima@example.test',
    ]);

    expect(fn () => app(CreateBooking::class)->handle($this->yoga, null, $this->slot, [
        'name' => 'Seconda Cliente',
        'email' => 'seconda@example.test',
    ]))->toThrow(SlotUnavailableException::class);

    expect(Booking::query()->count())->toBe(1);
});
