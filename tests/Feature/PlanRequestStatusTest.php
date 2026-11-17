<?php

use App\Enums\PlanRequestStatus;
use App\Mail\Platform\PlanRequestUpdatedMail;
use App\Mail\Platform\PlanUpgradeRequestMail;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\PlanRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();

    $this->base = Plan::factory()->create(['name' => 'Base', 'position' => 1]);
    $this->pro = Plan::factory()->create(['name' => 'Pro', 'position' => 2]);
    $this->studio = Plan::factory()->unlimited()->create(['name' => 'Studio', 'position' => 3]);

    $this->tenant = Tenant::factory()->create(['plan_id' => $this->base->id]);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->admin = User::factory()->admin()->create();
});

function openRequestOf(Tenant $tenant, User $manager, Plan $asked, array $changes = []): PlanRequest
{
    return PlanRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $manager->id,
        'requested_plan_id' => $asked->id,
        'requested_plan' => $asked->name,
        ...$changes,
    ]);
}

test('a request is kept with its state, and the administrators are written to', function () {
    $this->actingAs($this->manager)->post(route('app.plan.upgrade'), ['plan_id' => $this->pro->id, 'reason' => 'services', 'note' => 'Una seconda sede.'])->assertSessionHasNoErrors();

    $request = PlanRequest::query()->sole();

    expect($request->status)->toBe(PlanRequestStatus::New)
        ->and($request->tenant_id)->toBe($this->tenant->id)
        ->and($request->user_id)->toBe($this->manager->id)
        ->and($request->from_plan)->toBe('Base')
        ->and($request->requested_plan)->toBe('Pro')
        ->and($request->note)->toBe('Una seconda sede.');

    Mail::assertQueued(PlanUpgradeRequestMail::class, 1);
});

test('only one request waits at a time, and a new one is possible once it is answered or withdrawn', function () {
    $this->actingAs($this->manager)->post(route('app.plan.upgrade'), ['plan_id' => $this->pro->id])->assertSessionHasNoErrors();

    $this->post(route('app.plan.upgrade'), ['plan_id' => $this->studio->id])
        ->assertSessionHasErrors(['plan_id' => 'You already have a request waiting for an answer.']);

    $this->delete(route('app.plan.upgrade.cancel', PlanRequest::query()->sole()))->assertSessionHasNoErrors();

    $this->post(route('app.plan.upgrade'), ['plan_id' => $this->studio->id])->assertSessionHasNoErrors();

    expect(PlanRequest::query()->count())->toBe(2);
});

test('the manager withdraws their own open request and nobody else can', function () {
    $request = openRequestOf($this->tenant, $this->manager, $this->pro);
    $other = User::factory()->manager(Tenant::factory()->create())->create();

    $this->actingAs($other)->delete(route('app.plan.upgrade.cancel', $request))->assertForbidden();

    $this->actingAs($this->manager)->delete(route('app.plan.upgrade.cancel', $request))->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(PlanRequestStatus::Cancelled);

    $this->delete(route('app.plan.upgrade.cancel', $request))->assertNotFound();

    Mail::assertNotQueued(PlanRequestUpdatedMail::class);
});

test('the administrators see the waiting requests first and how many wait', function () {
    openRequestOf($this->tenant, $this->manager, $this->pro, ['status' => PlanRequestStatus::Rejected]);
    $waiting = openRequestOf(Tenant::factory()->create(['name' => 'Studio Verdi']), $this->manager, $this->studio);

    $this->actingAs($this->admin)
        ->get(route('admin.plan-requests'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plan-requests')
            ->where('requests.0.id', $waiting->id)
            ->where('requests.0.open', true)
            ->where('requests.1.status', 'rejected')
            ->where('planRequestsOpen', 1));
});

test('only an administrator reaches the requests, and only an administrator sees the count', function () {
    $this->actingAs($this->manager)->get(route('admin.plan-requests'))->assertForbidden();

    $this->get(route('app.portal'))->assertInertia(fn (Assert $page) => $page->where('planRequestsOpen', null));
});

test('an administrator takes a request in charge and the manager is told', function () {
    $request = openRequestOf($this->tenant, $this->manager, $this->pro);

    $this->actingAs($this->admin)->patch(route('admin.plan-requests.update', $request), ['status' => 'in_progress', 'response' => 'Ti scriviamo domani.'])->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(PlanRequestStatus::InProgress)
        ->and($request->fresh()->handled_by)->toBe($this->admin->id)
        ->and($request->fresh()->response)->toBe('Ti scriviamo domani.');

    Mail::assertQueued(PlanRequestUpdatedMail::class, fn (PlanRequestUpdatedMail $mail) => $mail->hasTo($this->manager->email));

    expect(ActivityLog::query()->where('action', 'plan.request_updated')->count())->toBe(1);
});

test('a rejection must say why, and an answered request cannot be answered again', function () {
    $request = openRequestOf($this->tenant, $this->manager, $this->pro);

    $this->actingAs($this->admin)->patch(route('admin.plan-requests.update', $request), ['status' => 'rejected', 'response' => ''])->assertSessionHasErrors('response');

    $this->patch(route('admin.plan-requests.update', $request), ['status' => 'rejected', 'response' => 'Il piano non è ancora disponibile.'])->assertSessionHasNoErrors();

    $this->patch(route('admin.plan-requests.update', $request), ['status' => 'handled'])->assertNotFound();

    expect($request->fresh()->status)->toBe(PlanRequestStatus::Rejected);
});

test('a manager cannot answer, and withdrawing is not an answer', function () {
    $request = openRequestOf($this->tenant, $this->manager, $this->pro);

    $this->actingAs($this->manager)->patch(route('admin.plan-requests.update', $request), ['status' => 'handled'])->assertForbidden();

    $this->actingAs($this->admin)->patch(route('admin.plan-requests.update', $request), ['status' => 'cancelled'])->assertSessionHasErrors('status');
});

test('the request closes by itself when the plan of the portal reaches what was asked', function () {
    $request = openRequestOf($this->tenant, $this->manager, $this->pro);

    $this->actingAs($this->admin)
        ->put(route('admin.portals.update', $this->tenant), [...portalForm(['name' => $this->tenant->name, 'slug' => $this->tenant->slug]), 'plan_id' => (string) $this->studio->id])
        ->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(PlanRequestStatus::Handled)
        ->and($request->fresh()->handled_by)->toBeNull();

    Mail::assertQueued(PlanRequestUpdatedMail::class, fn (PlanRequestUpdatedMail $mail) => $mail->hasTo($this->manager->email));
});

test('a plan that does not reach what was asked leaves the request open', function () {
    $request = openRequestOf($this->tenant, $this->manager, $this->studio);

    $this->actingAs($this->admin)
        ->put(route('admin.portals.update', $this->tenant), [...portalForm(['name' => $this->tenant->name, 'slug' => $this->tenant->slug]), 'plan_id' => (string) $this->pro->id])
        ->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(PlanRequestStatus::New);
});

test('the manager follows the request in the plan card and in the layout', function () {
    openRequestOf($this->tenant, $this->manager, $this->pro, ['status' => PlanRequestStatus::InProgress]);

    $this->actingAs($this->manager)
        ->get(route('app.portal'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('planRequest.status', 'in_progress')
            ->where('planRequest.open', true)
            ->where('auth.user.tenant.plan.openRequest.requested', 'Pro')
            ->where('auth.user.tenant.plan.openRequest.label', 'Taken in charge'));
});

test('the answer of an administrator reaches the manager in the card', function () {
    openRequestOf($this->tenant, $this->manager, $this->pro, ['status' => PlanRequestStatus::Rejected, 'response' => 'Non ancora disponibile.']);

    $this->actingAs($this->manager)
        ->get(route('app.portal'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('planRequest.open', false)
            ->where('planRequest.response', 'Non ancora disponibile.')
            ->where('auth.user.tenant.plan.openRequest', null));
});

test('the message to the manager says what happened and why', function () {
    $request = openRequestOf($this->tenant, $this->manager, $this->pro, ['status' => PlanRequestStatus::Rejected, 'response' => 'Il piano non è ancora disponibile.']);

    $html = (new PlanRequestUpdatedMail($request))->render();

    expect($html)->toContain('Pro')->toContain('Il piano non è ancora disponibile.');
});
