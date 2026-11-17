<?php

use App\Enums\PlanRequestStatus;
use App\Mail\Platform\PlanUpgradeRequestMail;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\PlanRequest;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\LayoutNotices;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();

    $this->base = Plan::factory()->create(['name' => 'Base', 'position' => 1, 'max_services' => 1, 'max_operators' => 1, 'max_bookings_per_month' => 10]);
    $this->pro = Plan::factory()->create(['name' => 'Pro', 'position' => 2, 'max_services' => 15, 'max_operators' => 5]);
    $this->studio = Plan::factory()->unlimited()->create(['name' => 'Studio', 'position' => 3]);

    $this->tenant = Tenant::factory()->create(['plan_id' => $this->base->id]);
    $this->manager = User::factory()->manager($this->tenant)->create(['name' => 'Marco Rossi']);
    $this->admin = User::factory()->admin()->create();
});

test('the manager asks for a bigger plan and every active administrator is told', function () {
    $second = User::factory()->admin()->create();
    User::factory()->admin()->create(['is_active' => false]);

    $this->actingAs($this->manager)
        ->post(route('app.plan.upgrade'), ['plan_id' => $this->pro->id, 'reason' => 'services', 'note' => 'Apriamo una seconda sede.'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Request sent. The platform administrator will get back to you.');

    Mail::assertQueued(PlanUpgradeRequestMail::class, 2);
    Mail::assertQueued(PlanUpgradeRequestMail::class, fn (PlanUpgradeRequestMail $mail) => $mail->hasTo($this->admin->email)
        && $mail->requested->is($this->pro)
        && $mail->current->is($this->base)
        && $mail->note === 'Apriamo una seconda sede.');
    Mail::assertQueued(PlanUpgradeRequestMail::class, fn (PlanUpgradeRequestMail $mail) => $mail->hasTo($second->email));
});

test('the register keeps the plans and the reason, never what the manager wrote', function () {
    $this->actingAs($this->manager)->post(route('app.plan.upgrade'), ['plan_id' => $this->studio->id, 'reason' => 'bookings', 'note' => 'Testo libero riservato']);

    $line = ActivityLog::query()->where('action', 'plan.upgrade_requested')->sole();

    expect($line->tenant_id)->toBe($this->tenant->id)
        ->and($line->context)->toMatchArray(['from' => 'Base', 'to' => 'Studio', 'reason' => 'bookings'])
        ->and(json_encode($line->context))->not->toContain('riservato');
});

test('only a plan above the current one on sale can be asked for', function (string $name) {
    $plans = ['same' => $this->base, 'unknown' => null];
    $retired = Plan::factory()->retired()->create(['name' => 'Vecchio', 'position' => 9]);
    $plans['retired'] = $retired;

    $this->actingAs($this->manager)
        ->post(route('app.plan.upgrade'), ['plan_id' => $plans[$name]?->id ?? 999999])
        ->assertSessionHasErrors('plan_id');

    Mail::assertNothingQueued();
})->with(['same', 'unknown', 'retired']);

test('a plan below the current one is refused too', function () {
    $this->tenant->update(['plan_id' => $this->pro->id]);

    $this->actingAs($this->manager)->post(route('app.plan.upgrade'), ['plan_id' => $this->base->id])->assertSessionHasErrors('plan_id');
});

test('the note and the reason are checked', function () {
    $this->actingAs($this->manager)
        ->post(route('app.plan.upgrade'), ['plan_id' => $this->pro->id, 'reason' => 'because', 'note' => str_repeat('a', 501)])
        ->assertSessionHasErrors(['reason', 'note']);
});

test('an operator and an administrator cannot ask: it is the manager who does', function () {
    $operator = User::factory()->operator($this->tenant)->create();

    $this->actingAs($operator)->post(route('app.plan.upgrade'), ['plan_id' => $this->pro->id])->assertForbidden();
    $this->actingAs($this->admin)->post(route('app.plan.upgrade'), ['plan_id' => $this->pro->id])->assertForbidden();

    Mail::assertNothingQueued();
});

test('a portal on the top plan, or without one, has nothing to ask for', function () {
    $this->tenant->update(['plan_id' => $this->studio->id]);

    $this->actingAs($this->manager)->post(route('app.plan.upgrade'), ['plan_id' => $this->pro->id])->assertSessionHasErrors('plan_id');
    $this->tenant->update(['plan_id' => null]);
    expect($this->tenant->fresh()->upgradePlans())->toBeEmpty();
});

test('the layout offers the manager the plans above, without prices, and nobody else', function () {
    $this->actingAs($this->manager)
        ->get(route('app.portal'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.tenant.plan.name', 'Base')
            ->has('auth.user.tenant.plan.upgrades', 2)
            ->where('auth.user.tenant.plan.upgrades.0.name', 'Pro')
            ->where('auth.user.tenant.plan.upgrades.0.includes', fn ($list) => collect($list)->contains('Shared resources'))
            ->missing('auth.user.tenant.plan.upgrades.0.price'));

    $operator = User::factory()->operator($this->tenant)->create();

    $this->actingAs($operator)->get(route('app.bookings'))->assertInertia(fn (Assert $page) => $page->where('auth.user.tenant.plan', null));
});

test('the notice for the monthly bookings points to the plan, only when there is a bigger one', function () {
    $service = Service::factory()->for($this->tenant)->create();
    Booking::factory()->for($service)->count(9)->create(['user_id' => $this->manager->id]);

    $notice = collect(LayoutNotices::for($this->manager))->firstWhere('key', 'monthly-bookings');

    expect($notice['link']['href'])->toEndWith('/app/portal#your-plan');

    $this->tenant->update(['plan_id' => $this->studio->id]);
    $this->studio->update(['max_bookings_per_month' => 10]);

    $notice = collect(LayoutNotices::for($this->manager->fresh()))->firstWhere('key', 'monthly-bookings');

    expect($notice['link'])->toBeNull();
});

test('the refusal at the limit of services and operators carries the sign for the request button', function () {
    Service::factory()->for($this->tenant)->create();
    User::factory()->operator($this->tenant)->create();

    $this->actingAs($this->manager)
        ->post(route('app.services.store'), serviceForm($this->tenant, ['name' => 'Secondo servizio']))
        ->assertSessionHasErrors(['name', 'plan_limit']);

    $this->post(route('app.staff.store'), ['name' => 'Giulia Bianchi', 'email' => 'giulia@example.com', 'timezone' => 'Europe/Rome', 'locale' => 'it', 'is_bookable' => true, 'is_active' => true])
        ->assertSessionHasErrors(['name', 'plan_limit']);
});

test('the request is limited to five an hour', function () {
    $this->actingAs($this->manager);

    foreach (range(1, 5) as $ignored) {
        $this->post(route('app.plan.upgrade'), ['plan_id' => $this->pro->id])->assertSessionHasNoErrors();

        // One request waits at a time: answer it so the next attempt reaches the limit, not the rule.
        PlanRequest::query()->update(['status' => PlanRequestStatus::Handled]);
    }

    $this->post(route('app.plan.upgrade'), ['plan_id' => $this->pro->id])->assertStatus(429);
});
