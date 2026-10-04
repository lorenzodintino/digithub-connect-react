<?php

use App\Enums\SubscriptionState;
use App\Mail\Platform\SubscriptionNoticeMail;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 09:00', 'Europe/Rome'));
    $this->admin = User::factory()->admin()->create();
    $this->tenant = Tenant::factory()->create(['name' => 'Studio Rossi', 'timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create(['locale' => 'it']);
});

test('the state follows the last paid day, the reminder days and the tolerance', function (?string $paidUntil, SubscriptionState $state) {
    $this->tenant->forceFill(['paid_until' => $paidUntil])->save();

    expect($this->tenant->fresh()->subscriptionState())->toBe($state);
})->with([
    'no end date' => [null, SubscriptionState::Open],
    'fifteen days left' => ['2026-10-25', SubscriptionState::Active],
    'fourteen days left' => ['2026-10-24', SubscriptionState::Expiring],
    'last paid day' => ['2026-10-10', SubscriptionState::Expiring],
    'first day of tolerance' => ['2026-10-09', SubscriptionState::Grace],
    'last day of tolerance' => ['2026-10-03', SubscriptionState::Grace],
    'past the tolerance' => ['2026-10-02', SubscriptionState::Lapsed],
]);

test('the administrator records the paid period and the reminders start over', function () {
    $this->tenant->forceFill(['paid_until' => '2026-10-12', 'subscription_reminders_sent' => 2])->save();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.portal', ['tenant' => $this->tenant])
        ->set('paidUntil', '2027-10-12')
        ->call('savePaidUntil')
        ->assertHasNoErrors();

    $tenant = $this->tenant->fresh();
    expect($tenant->paid_until->toDateString())->toBe('2027-10-12');
    expect($tenant->subscription_reminders_sent)->toBe(0);
    expect(ActivityLog::query()->where('action', 'portal.paid_until')->sole()->context)->toMatchArray(['from' => '2026-10-12', 'to' => '2027-10-12']);
});

test('a portal suspended for non-payment comes back once the payment covers today', function () {
    $this->tenant->forceFill(['paid_until' => '2026-09-01', 'is_active' => false, 'subscription_suspended_at' => now()])->save();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.portal', ['tenant' => $this->tenant])
        ->set('paidUntil', '2026-11-30')
        ->call('savePaidUntil');

    expect($this->tenant->fresh())
        ->is_active->toBeTrue()
        ->subscription_suspended_at->toBeNull();
});

test('a portal suspended by hand stays suspended when the payment is recorded', function () {
    $this->tenant->update(['is_active' => false]);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.portal', ['tenant' => $this->tenant])
        ->set('paidUntil', '2026-11-30')
        ->call('savePaidUntil');

    expect($this->tenant->fresh()->is_active)->toBeFalse();
});

test('the manager gets one reminder per reminder day, however often the command runs', function () {
    Mail::fake();
    $this->tenant->forceFill(['paid_until' => '2026-10-20'])->save();

    $this->artisan('portals:check-subscriptions')->assertSuccessful();
    $this->artisan('portals:check-subscriptions')->assertSuccessful();

    Mail::assertQueuedCount(1);
    Mail::assertQueued(SubscriptionNoticeMail::class, fn (SubscriptionNoticeMail $mail) => $mail->notice === SubscriptionNoticeMail::EXPIRING && $mail->hasTo($this->manager->email));

    $this->travelTo(CarbonImmutable::parse('2026-10-18 09:00', 'Europe/Rome'));
    $this->artisan('portals:check-subscriptions')->assertSuccessful();

    Mail::assertQueuedCount(2);
});

test('a skipped run sends only the reminder due now', function () {
    Mail::fake();
    $this->tenant->forceFill(['paid_until' => '2026-10-11'])->save();

    $this->artisan('portals:check-subscriptions')->assertSuccessful();

    Mail::assertQueuedCount(1);
    expect($this->tenant->fresh()->subscription_reminders_sent)->toBe(2);
});

test('the tolerance starts with its own notice', function () {
    Mail::fake();
    $this->tenant->forceFill(['paid_until' => '2026-10-08', 'subscription_reminders_sent' => 2])->save();

    $this->artisan('portals:check-subscriptions')->assertSuccessful();

    Mail::assertQueued(SubscriptionNoticeMail::class, fn (SubscriptionNoticeMail $mail) => $mail->notice === SubscriptionNoticeMail::GRACE);
    expect($this->tenant->fresh()->is_active)->toBeTrue();
});

test('a portal past its tolerance is suspended, logged and told', function () {
    Mail::fake();
    $this->tenant->forceFill(['paid_until' => '2026-10-02', 'subscription_reminders_sent' => 3])->save();

    $this->artisan('portals:check-subscriptions')->assertSuccessful();

    $tenant = $this->tenant->fresh();
    expect($tenant->is_active)->toBeFalse();
    expect($tenant->subscription_suspended_at)->not->toBeNull();
    expect(ActivityLog::query()->where('action', 'portal.suspended')->sole()->context)->toMatchArray(['reason' => 'subscription']);
    Mail::assertQueued(SubscriptionNoticeMail::class, fn (SubscriptionNoticeMail $mail) => $mail->notice === SubscriptionNoticeMail::SUSPENDED);
    $this->get(route('portal.show', $tenant))->assertNotFound();
});

test('portals without an end date are left alone', function () {
    Mail::fake();

    $this->artisan('portals:check-subscriptions')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($this->tenant->fresh()->is_active)->toBeTrue();
});

test('each notice reads right in the manager language after the queue', function (string $notice, string $expected) {
    $this->tenant->forceFill(['paid_until' => '2026-10-08'])->save();
    $mail = unserialize(serialize(new SubscriptionNoticeMail($this->tenant->fresh(), $notice)));

    expect($mail->locale('it')->render())->toContain($expected)->toContain('Studio Rossi');
})->with([
    'expiring' => [SubscriptionNoticeMail::EXPIRING, 'termina il 08 ottobre 2026'],
    'grace' => [SubscriptionNoticeMail::GRACE, 'Il 16 ottobre 2026 il portale verrà sospeso'],
    'suspended' => [SubscriptionNoticeMail::SUSPENDED, 'non è stato rinnovato'],
]);

test('the manager sees how long is left, and the tolerance in red', function (string $paidUntil, string $notice) {
    $this->tenant->forceFill(['paid_until' => $paidUntil])->save();

    $this->actingAs($this->manager)->get(route('app.services'))->assertOk()->assertSee($notice);
})->with([
    'five days left' => ['2026-10-15', 'Il periodo pagato termina tra 5 giorni, il 15 ott 2026'],
    'last paid day' => ['2026-10-10', 'Il periodo pagato termina oggi'],
    'in tolerance' => ['2026-10-08', 'Il portale verrà sospeso il 16 ott 2026'],
]);
