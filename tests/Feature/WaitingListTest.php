<?php

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\NotifyWaitingList;
use App\Mail\Customer\WaitingListOpeningMail;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();

    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();

    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 60,
        'slot_interval_minutes' => 60,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'cancellation_notice_hours' => 0,
    ]);

    $this->service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
        ]);
    }

    $this->slot = (new SlotFinder($this->service, $this->operator))
        ->slotsForDay(CarbonImmutable::now($this->tenant->timezone)->addDays(3)->startOfDay())[0];
});

/**
 * Somebody waiting for this service over the given period.
 */
function waiter(array $attributes = []): WaitingListEntry
{
    return WaitingListEntry::factory()->create([
        'tenant_id' => test()->tenant->id,
        'service_id' => test()->service->id,
        'wanted_from' => CarbonImmutable::now(test()->tenant->timezone)->toDateString(),
        'wanted_to' => CarbonImmutable::now(test()->tenant->timezone)->addDays(10)->toDateString(),
        ...$attributes,
    ]);
}

test('a visitor joins the waiting list from the public page', function () {
    Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->service])
        ->call('startWaiting')
        ->set('waitingName', 'Anna Ferrari')
        ->set('waitingEmail', 'anna@example.test')
        ->set('waitingFrom', CarbonImmutable::now($this->tenant->timezone)->toDateString())
        ->set('waitingTo', CarbonImmutable::now($this->tenant->timezone)->addDays(20)->toDateString())
        ->set('waitingPrivacyAccepted', true)
        ->call('joinWaitingList')
        ->assertHasNoErrors();

    $entry = WaitingListEntry::query()->sole();

    expect($entry->customer_name)->toBe('Anna Ferrari')
        ->and($entry->service_id)->toBe($this->service->id)
        ->and($entry->privacy_accepted_at)->not->toBeNull();
});

test('waiting list controls identify the search and the customer being removed', function () {
    waiter(['customer_name' => 'Anna Ferrari']);

    $this->actingAs($this->manager)
        ->get(route('app.waiting-list'))
        ->assertSee('label="Search"', escape: false)
        ->assertSee('aria-label="Remove Anna Ferrari from the waiting list"', escape: false);
});

test('the waiting list asks for consent like a booking does', function () {
    Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->service])
        ->call('startWaiting')
        ->set('waitingName', 'Anna Ferrari')
        ->set('waitingEmail', 'anna@example.test')
        ->call('joinWaitingList')
        ->assertHasErrors('waitingPrivacyAccepted');

    expect(WaitingListEntry::query()->count())->toBe(0);
});

test('asking twice moves the dates instead of queueing twice', function () {
    waiter(['customer_email' => 'anna@example.test']);

    Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->service])
        ->call('startWaiting')
        ->set('waitingName', 'Anna Ferrari')
        ->set('waitingEmail', 'anna@example.test')
        ->set('waitingFrom', CarbonImmutable::now($this->tenant->timezone)->addDays(30)->toDateString())
        ->set('waitingTo', CarbonImmutable::now($this->tenant->timezone)->addDays(40)->toDateString())
        ->set('waitingPrivacyAccepted', true)
        ->call('joinWaitingList')
        ->assertHasNoErrors();

    expect(WaitingListEntry::query()->count())->toBe(1)
        ->and(WaitingListEntry::query()->sole()->wanted_from->toDateString())
        ->toBe(CarbonImmutable::now($this->tenant->timezone)->addDays(30)->toDateString());
});

test('email casing cannot create another waiting list entry', function (string $storedEmail) {
    $existing = waiter(['customer_email' => $storedEmail]);

    Livewire::test('pages::portal.service', ['tenant' => $this->tenant, 'service' => $this->service])
        ->call('startWaiting')
        ->set('waitingName', 'Anna Ferrari')
        ->set('waitingEmail', ' ANNA@EXAMPLE.TEST ')
        ->set('waitingFrom', CarbonImmutable::now($this->tenant->timezone)->addDays(30)->toDateString())
        ->set('waitingTo', CarbonImmutable::now($this->tenant->timezone)->addDays(40)->toDateString())
        ->set('waitingPrivacyAccepted', true)
        ->call('joinWaitingList')
        ->assertHasNoErrors();

    expect(WaitingListEntry::query()->sole()->id)->toBe($existing->id)
        ->and($existing->fresh()->wanted_from->toDateString())
        ->toBe(CarbonImmutable::now($this->tenant->timezone)->addDays(30)->toDateString());
})->with(['anna@example.test', 'ANNA@example.test']);

test('legacy waiting list duplicates receive only one opening email', function () {
    $first = waiter(['customer_email' => 'anna@example.test']);
    $second = waiter(['customer_email' => 'ANNA@EXAMPLE.TEST']);

    expect(app(NotifyWaitingList::class)->handle($this->service, $this->slot))->toBe(1);

    Mail::assertQueued(WaitingListOpeningMail::class, 1);
    expect($first->fresh()->notified_at)->not->toBeNull()
        ->and($second->fresh()->notified_at)->not->toBeNull();
    expect(app(NotifyWaitingList::class)->handle($this->service, $this->slot))->toBe(0);
});

test('a recent notification also silences a legacy email casing duplicate', function () {
    waiter(['customer_email' => 'anna@example.test', 'notified_at' => now()->subHour()]);
    waiter(['customer_email' => 'ANNA@EXAMPLE.TEST']);

    expect(app(NotifyWaitingList::class)->handle($this->service, $this->slot))->toBe(0);

    Mail::assertNotQueued(WaitingListOpeningMail::class);
});

test('a cancellation writes to everybody waiting for that day', function () {
    $first = waiter(['customer_email' => 'prima@example.test']);
    $second = waiter(['customer_email' => 'seconda@example.test']);

    // Wants another month: not their problem.
    $other = waiter([
        'customer_email' => 'altra@example.test',
        'wanted_from' => CarbonImmutable::now($this->tenant->timezone)->addDays(60)->toDateString(),
        'wanted_to' => CarbonImmutable::now($this->tenant->timezone)->addDays(70)->toDateString(),
    ]);

    $booking = Booking::factory()->forSlot($this->service, $this->operator, $this->slot)->create();

    app(CancelBooking::class)->handle($booking, $this->manager, 'Il cliente ha chiamato');

    Mail::assertQueued(WaitingListOpeningMail::class, 2);
    Mail::assertQueued(WaitingListOpeningMail::class, fn ($mail) => $mail->hasTo('prima@example.test'));
    Mail::assertQueued(WaitingListOpeningMail::class, fn ($mail) => $mail->hasTo('seconda@example.test'));
    Mail::assertNotQueued(WaitingListOpeningMail::class, fn ($mail) => $mail->hasTo('altra@example.test'));

    expect($first->fresh()->notified_at)->not->toBeNull()
        ->and($second->fresh()->notified_at)->not->toBeNull()
        ->and($other->fresh()->notified_at)->toBeNull();
});

test('somebody just told is left alone for a while', function () {
    waiter(['customer_email' => 'prima@example.test', 'notified_at' => now()->subHour()]);

    $booking = Booking::factory()->forSlot($this->service, $this->operator, $this->slot)->create();

    app(CancelBooking::class)->handle($booking, $this->manager, 'Annullata');

    // The customer still gets their cancellation notice; what must not go out
    // is a second waiting list email an hour after the first.
    Mail::assertNotQueued(WaitingListOpeningMail::class);
});

test('the queued opening email renders the slot in the customer timezone', function () {
    $entry = waiter(['customer_timezone' => 'Asia/Kolkata']);
    $mail = unserialize(serialize(new WaitingListOpeningMail($entry, '2026-10-26T08:00:00+00:00')));

    $mail->assertSeeInHtml('13:30');
    $mail->assertSeeInHtml(route('portal.service', [$this->tenant, $this->service]).'?d=2026-10-26');
    $mail->assertSeeInHtml(route('portal.waiting-list.leave', [$this->tenant, $entry]));
});

test('the link in the email takes somebody off the list', function () {
    // The page speaks the language of the person who joined.
    $entry = waiter(['customer_email' => 'anna@example.test', 'customer_locale' => 'en']);

    $this->get(route('portal.waiting-list.leave', [$this->tenant, $entry]))
        ->assertOk()
        ->assertSee('Leave this waiting list?');

    // The page asks first: only the button actually removes them.
    Livewire::test('pages::portal.waiting-list', ['tenant' => $this->tenant, 'entry' => $entry])
        ->call('leave')
        ->assertSee('You are off the waiting list');

    expect(WaitingListEntry::query()->count())->toBe(0);
});

test('an entry of another portal is out of reach', function () {
    $other = Tenant::factory()->create();
    $entry = waiter(['customer_email' => 'anna@example.test']);

    $this->get(route('portal.waiting-list.leave', [$other->slug, $entry->uid]))->assertNotFound();

    expect(WaitingListEntry::query()->count())->toBe(1);
});

test('a manager reads the list and takes somebody off it', function () {
    $entry = waiter(['customer_name' => 'Anna Ferrari', 'customer_email' => 'anna@example.test']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.waiting-list')
        ->assertSee('Anna Ferrari')
        ->call('remove', $entry->id);

    expect(WaitingListEntry::query()->count())->toBe(0);
});

test('an operator has no waiting list to read', function () {
    Livewire::actingAs($this->operator)
        ->test('pages::app.waiting-list')
        ->assertForbidden();
});

test('entries whose period has gone by are pruned', function () {
    $past = waiter([
        'customer_email' => 'vecchia@example.test',
        'wanted_from' => CarbonImmutable::now()->subDays(40)->toDateString(),
        'wanted_to' => CarbonImmutable::now()->subDays(30)->toDateString(),
    ]);

    $current = waiter(['customer_email' => 'attuale@example.test']);

    $this->artisan('waiting-list:prune')->assertSuccessful();

    expect(WaitingListEntry::query()->whereKey($past->id)->exists())->toBeFalse()
        ->and(WaitingListEntry::query()->whereKey($current->id)->exists())->toBeTrue();
});

test('erasing a customer takes their waiting list entry too', function () {
    waiter(['customer_email' => 'anna@example.test']);

    $booking = Booking::factory()
        ->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->subDays(5)->setTime(10, 0))
        ->create(['customer_email' => 'anna@example.test']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('askToAnonymise', $booking->id)
        ->call('anonymiseCustomer');

    expect(WaitingListEntry::query()->count())->toBe(0);
});
