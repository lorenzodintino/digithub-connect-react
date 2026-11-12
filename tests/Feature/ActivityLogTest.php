<?php

use App\Actions\Bookings\CancelBooking;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome', 'name' => 'Studio Rossi']);
    $this->manager = User::factory()->manager($this->tenant)->create(['name' => 'Marco Rossi']);
    $this->operator = User::factory()->operator($this->tenant)->create(['name' => 'Giulia Bianchi']);
    $this->admin = User::factory()->admin()->create(['name' => 'Platform Admin']);

    $this->service = Service::factory()->for($this->tenant)->create([
        'name' => 'Prima consulenza',
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
});

/**
 * A booking of this portal, a few days out.
 */
function loggedBooking(): Booking
{
    $slot = (new SlotFinder(test()->service, test()->operator))
        ->slotsForDay(CarbonImmutable::now(test()->tenant->timezone)->addDays(2)->startOfDay())[0];

    return Booking::factory()->forSlot(test()->service, test()->operator, $slot)->create();
}

test('booking a slot leaves a line in the register', function () {
    $slot = (new SlotFinder($this->service, $this->operator))
        ->slotsForDay(CarbonImmutable::now($this->tenant->timezone)->addDays(2)->startOfDay())[0];

    $this->post(route('portal.service.book', [$this->tenant, $this->service]), bookingForm($slot, ['name' => 'Anna Ferrari', 'email' => 'anna@example.test']))
        ->assertSessionHasNoErrors();

    $log = ActivityLog::query()->where('action', 'booking.created')->sole();

    expect($log->tenant_id)->toBe($this->tenant->id)
        // Nobody was signed in: the public page is the system acting.
        ->and($log->user_id)->toBeNull()
        ->and($log->context['service'])->toBe('Prima consulenza')
        // The label points at the booking by service and time: no customer, and not the uid,
        // which is the secret of the customer's own link.
        ->and($log->subject_label)->toBe('Prima consulenza · '.Booking::query()->sole()->starts_at->setTimezone('Europe/Rome')->format('Y-m-d H:i'))
        ->and($log->subject_label)->not->toContain(Booking::query()->sole()->uid);
});

test('the register never copies the customer into itself', function () {
    $booking = loggedBooking();
    $booking->update(['customer_name' => 'Anna Ferrari', 'customer_email' => 'anna@example.test']);

    app(CancelBooking::class)->handle($booking, $this->manager, 'Il cliente ha chiamato');

    $log = ActivityLog::query()->where('action', 'booking.cancelled')->sole();

    expect(json_encode($log->context))->not->toContain('Anna Ferrari')
        ->and($log->subject_label)->not->toContain('anna@example.test')
        ->and($log->actor_name)->toBe('Marco Rossi')
        ->and($log->context['by'])->toBe('portal');
});

test('a cancellation from the customer link is marked as theirs', function () {
    $booking = loggedBooking();

    app(CancelBooking::class)->handle($booking);

    expect(ActivityLog::query()->where('action', 'booking.cancelled')->sole()->context['by'])->toBe('customer');
});

test('signing in and failing to sign in are both recorded', function () {
    // The failed one first: signing in leaves a session behind, and the point
    // here is to see the two lines, not to model a real visit.
    $this->post(route('login'), ['email' => $this->manager->email, 'password' => 'sbagliata']);

    $this->post(route('login'), ['email' => $this->manager->email, 'password' => 'password']);

    expect(ActivityLog::query()->where('action', 'auth.login')->count())->toBe(1)
        ->and(ActivityLog::query()->where('action', 'auth.failed')->sole()->context['email'])->toBe($this->manager->email);
});

test('changing a service is recorded with who did it', function () {
    $this->actingAs($this->manager)
        ->put(route('app.services.update', $this->service->slug), serviceForm($this->service, ['name' => 'Consulenza estesa']))
        ->assertSessionHasNoErrors();

    $log = ActivityLog::query()->where('action', 'service.updated')->sole();

    expect($log->user_id)->toBe($this->manager->id)
        ->and($log->actor_role)->toBe('manager')
        ->and($log->subject_label)->toBe('Consulenza estesa');
});

test('a manager reads the register of their own portal only', function () {
    $stranger = Tenant::factory()->create(['name' => 'Altro Studio']);
    ActivityLog::factory()->create(['tenant_id' => $stranger->id, 'actor_name' => 'Estraneo', 'action' => 'service.created']);

    ActivityLog::factory()->create(['tenant_id' => $this->tenant->id, 'actor_name' => 'Marco Rossi', 'action' => 'service.created']);

    $this->actingAs($this->manager)
        ->get(route('app.activity'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('shared/activity')
            ->has('entries.data', 1)
            ->where('entries.data.0.actor', 'Marco Rossi')
            ->where('seesEverything', false));
});

test('the references of an entry are labelled in the language of the reader', function () {
    $this->manager->update(['locale' => 'it']);
    ActivityLog::factory()->create([
        'tenant_id' => $this->tenant->id,
        'action' => 'portal.license_exempt',
        'context' => ['service' => 'Prima consulenza', 'by' => 'portal', 'exempt' => true, 'fields' => ['name', 'slug'], 'unlisted_key' => 'x'],
    ]);

    $this->actingAs($this->manager)->get(route('app.activity'))->assertInertia(fn (Assert $page) => $page
        ->where('entries.data.0.context', ['Servizio: Prima consulenza', 'Fatto da: portal', 'Fuori dalla licenza: Sì', 'Campi: name, slug', 'unlisted_key: x']));

    $this->manager->update(['locale' => 'en']);

    $this->get(route('app.activity'))->assertInertia(fn (Assert $page) => $page
        ->where('entries.data.0.context.0', 'Service: Prima consulenza')
        ->where('entries.data.0.context.2', 'Exempt from the license: Yes'));
});

test('the administrator reads everything and can filter by portal', function () {
    $stranger = Tenant::factory()->create(['name' => 'Altro Studio']);
    ActivityLog::factory()->create(['tenant_id' => $stranger->id, 'actor_name' => 'Estraneo', 'action' => 'service.created']);
    ActivityLog::factory()->create(['tenant_id' => $this->tenant->id, 'actor_name' => 'Marco Rossi', 'action' => 'service.created']);

    $this->actingAs($this->admin);

    $this->get(route('admin.activity'))->assertInertia(fn (Assert $page) => $page
        ->has('entries.data', 2)
        ->where('seesEverything', true));

    $this->get(route('admin.activity', ['portal' => $stranger->id]))->assertInertia(fn (Assert $page) => $page
        ->has('entries.data', 1)
        ->where('entries.data.0.actor', 'Estraneo')
        ->where('entries.data.0.portal', 'Altro Studio'));
});

test('the register filters by kind, person and date', function () {
    ActivityLog::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->manager->id,
        'actor_name' => 'Marco Rossi',
        'action' => 'service.created',
        'subject_label' => 'Servizio nuovo',
        'created_at' => now()->subDays(10),
    ]);

    ActivityLog::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->operator->id,
        'actor_name' => 'Giulia Bianchi',
        'action' => 'booking.confirmed',
        'subject_label' => 'Prenotazione',
        'created_at' => now(),
    ]);

    $this->actingAs($this->manager);
    $listed = fn (array $filters, string $subject) => $this->get(route('app.activity', $filters))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('entries.data.0.subject', $subject));

    $listed(['group' => 'booking'], 'Prenotazione');
    $listed(['actor' => $this->manager->id], 'Servizio nuovo');
    $listed(['from' => now()->subDays(2)->toDateString()], 'Prenotazione');
    $listed(['to' => now()->subDays(2)->toDateString()], 'Servizio nuovo');
});

test('nonsense in the filters is ignored rather than breaking the page', function () {
    ActivityLog::factory()->create(['tenant_id' => $this->tenant->id, 'subject_label' => 'Servizio nuovo']);

    $this->actingAs($this->manager)
        ->get(route('app.activity').'?from=31-02-2026&to=2026-02-31&q[]=x&portal='.$this->tenant->id)
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('filters', ['q' => '', 'group' => 'all', 'portal' => 'all', 'actor' => 'all', 'from' => '', 'to' => '']));
});

test('an operator has no register to read', function () {
    $this->actingAs($this->operator)->get(route('app.activity'))->assertForbidden();
});

test('lines older than a year are pruned', function () {
    $old = ActivityLog::factory()->create(['created_at' => now()->subDays(400)]);
    $recent = ActivityLog::factory()->create(['created_at' => now()->subDays(30)]);

    $this->artisan('activity:prune')->assertSuccessful();

    expect(ActivityLog::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(ActivityLog::query()->whereKey($recent->id)->exists())->toBeTrue();
});

test('a deleted account keeps its history readable', function () {
    ActivityLog::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->operator->id,
        'actor_name' => 'Giulia Bianchi',
    ]);

    $this->operator->delete();

    $log = ActivityLog::query()->where('actor_name', 'Giulia Bianchi')->sole();

    expect($log->user_id)->toBeNull()
        ->and($log->actor_name)->toBe('Giulia Bianchi');
});
