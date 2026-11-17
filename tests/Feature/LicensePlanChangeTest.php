<?php

use App\Enums\LicenseOutcome;
use App\Models\Plan;
use App\Models\Resource;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantLicense;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Services\LicenseService;
use App\Support\LayoutNotices;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * «Cambia piano a metà periodo» of the License Manager: the plan and the expiry of the answer change at
 * once, a period of a kind Atrio does not know (PLAN_CHANGE) appears, and a closed period carries `endedAt`.
 * Atrio reads the plan from `license.planType` and the verdict from `access` only, never from the periods;
 * a move to a plan with lower limits never deletes or hides what the portal already has.
 */
beforeEach(function () {
    config(['services.license.enforcement' => 'on']);

    Plan::factory()->create(['name' => 'Base', 'position' => 1]);
    Plan::factory()->create(['name' => 'Pro', 'position' => 2]);
    Plan::factory()->unlimited()->create(['name' => 'Studio', 'position' => 3]);

    $this->tenant = Tenant::factory()->create();
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->admin = User::factory()->admin()->create();
    $this->license = app(LicenseService::class);

    $this->reply = [];
    Http::fake(['license.test/*' => fn () => Http::response($this->reply)]);
});

/**
 * The answer of the License Manager right after a change from Pro to Base in the middle of the period.
 *
 * @param  array<string, mixed>  $attributes  the attributes of the plan now in force, by the keys of the License Manager
 * @return array<string, mixed>
 */
function afterPlanChange(array $attributes, string $code = 'base', string $name = 'Base'): array
{
    $answer = licenseAnswer(
        ['expiresAt' => now()->addYear()->toIso8601String(), 'graceEndsAt' => now()->addYear()->addDays(7)->toIso8601String()],
        ['planType' => ['id' => 'pt_'.$code, 'code' => $code, 'name' => $name, 'prices' => [], 'attributes' => $attributes]],
    );

    $answer['data']['periods'] = [
        // The period that was cut short by the change: closed early, with the date it ended.
        ['id' => 'per_1', 'kind' => 'ISSUE', 'source' => 'MANUAL', 'planType' => ['id' => 'pt_pro', 'code' => 'pro', 'name' => 'Pro'], 'months' => 12, 'amountCents' => 39000, 'previousExpiresAt' => null, 'newExpiresAt' => now()->addMonths(9)->toIso8601String(), 'endedAt' => now()->subHour()->toIso8601String(), 'voidedAt' => null, 'voidReason' => null, 'createdAt' => now()->subMonths(3)->toIso8601String(), 'corrections' => []],
        // The new one, of a kind that did not exist: it must be read like any other, or ignored.
        ['id' => 'per_2', 'kind' => 'PLAN_CHANGE', 'source' => 'MANUAL', 'planType' => ['id' => 'pt_'.$code, 'code' => $code, 'name' => $name], 'months' => 12, 'amountCents' => 19000, 'previousExpiresAt' => null, 'newExpiresAt' => now()->addYear()->toIso8601String(), 'endedAt' => null, 'voidedAt' => null, 'voidReason' => null, 'createdAt' => now()->toIso8601String(), 'corrections' => []],
    ];

    return $answer;
}

/** A portal that has built things on a bigger plan: more than the lower plan includes. */
function portalWithMuchBuilt(Tenant $tenant): void
{
    Service::factory()->for($tenant)->count(5)->create(['capacity' => 1]);
    Service::factory()->for($tenant)->create(['name' => 'Yoga', 'capacity' => 6]);
    Service::factory()->for($tenant)->create(['name' => 'Pacchetto', 'is_package' => true, 'capacity' => 1]);
    User::factory()->operator($tenant)->count(3)->create();
    Resource::factory()->for($tenant)->count(2)->create();
    WaitingListEntry::factory()->count(2)->create(['tenant_id' => $tenant->id, 'service_id' => Service::query()->where('tenant_id', $tenant->id)->first()->id]);
}

$base = ['max_servizi' => 3, 'max_operatori' => 1, 'max_prenotazioni_mese' => 100, 'max_lista_attesa' => 0, 'max_pacchetti' => 0, 'is_risorse' => false, 'is_lista_attesa' => false, 'is_pacchetti' => false, 'is_servizi_gruppo' => false, 'is_pulsanti_calendario' => false, 'is_embed_link' => false, 'is_custom_smtp' => false];

test('an answer with a PLAN_CHANGE period and a period closed early is read like any other', function () use ($base) {
    $this->reply = afterPlanChange($base);

    $status = $this->license->refresh($this->tenant);
    $copy = $this->tenant->fresh()->license;

    expect($status->outcome)->toBe(LicenseOutcome::Licensed)
        ->and($status->error)->toBeNull()
        ->and($status->allowed)->toBeTrue()
        ->and($copy->last_error)->toBeNull()
        ->and($copy->plan_code)->toBe('base')
        ->and($copy->expires_at->isFuture())->toBeTrue();
});

test('the plan is the one of license.planType, never the one of the last period', function () use ($base) {
    $answer = afterPlanChange($base);
    // The last period of the list still names the old plan: it must not matter.
    $answer['data']['periods'][1]['planType'] = ['id' => 'pt_pro', 'code' => 'pro', 'name' => 'Pro'];
    $this->reply = $answer;

    $this->license->refresh($this->tenant);

    $plan = $this->license->planFor($this->tenant->fresh());

    expect($plan->code)->toBe('base')->and($plan->max_services)->toBe(3)->and($plan->allows('resources'))->toBeFalse();
});

test('the new expiry of the change is the one that counts', function () use ($base) {
    TenantLicense::factory()->for($this->tenant)->create(['expires_at' => now()->addMonths(2), 'plan_code' => 'pro', 'plan_name' => 'Pro']);
    $this->reply = afterPlanChange($base);

    $this->license->refresh($this->tenant);

    expect($this->tenant->fresh()->license->expires_at->greaterThan(now()->addMonths(11)))->toBeTrue();
});

test('a downgrade to lower limits keeps everything that exists and refuses only what is new', function () use ($base) {
    portalWithMuchBuilt($this->tenant);
    $this->reply = afterPlanChange($base);

    $before = ['services' => Service::query()->count(), 'operators' => User::query()->where('tenant_id', $this->tenant->id)->count(), 'resources' => Resource::query()->count(), 'waiting' => WaitingListEntry::query()->count()];

    $this->license->refresh($this->tenant);

    $tenant = $this->tenant->fresh();

    // The limits are those of the plan now in force...
    expect($tenant->planUsage()->serviceLimit())->toBe(3)
        ->and($tenant->planUsage()->operatorLimit())->toBe(1)
        ->and($tenant->planAllows('resources'))->toBeFalse()
        ->and($tenant->planAllows('group_services'))->toBeFalse();

    // ...nothing was deleted or hidden...
    expect(Service::query()->count())->toBe($before['services'])
        ->and(User::query()->where('tenant_id', $this->tenant->id)->count())->toBe($before['operators'])
        ->and(Resource::query()->count())->toBe($before['resources'])
        ->and(WaitingListEntry::query()->count())->toBe($before['waiting']);

    // ...and only the new is refused.
    $this->actingAs($this->manager->fresh())
        ->post(route('app.services.store'), serviceForm($tenant, ['name' => 'Uno in più']))
        ->assertSessionHasErrors(['name', 'plan_limit']);
    $this->post(route('app.staff.store'), ['name' => 'Giulia Bianchi', 'email' => 'giulia@example.com', 'timezone' => 'Europe/Rome', 'locale' => 'it', 'is_bookable' => true, 'is_active' => true])
        ->assertSessionHasErrors(['name', 'plan_limit']);
    $this->post(route('app.resources.store'), ['name' => 'Cabina nuova', 'type' => 'room', 'description' => '', 'notes' => '', 'preparation_minutes' => 0, 'cleanup_minutes' => 0, 'is_active' => true])->assertForbidden();

    // What exists is still editable: a group service stays a group one, a service can be renamed.
    $yoga = Service::query()->where('name', 'Yoga')->sole();
    $this->put(route('app.services.update', $yoga->slug), serviceForm($yoga, ['capacity' => 7]))->assertSessionHasNoErrors();

    expect($yoga->fresh()->capacity)->toBe(7);
});

test('the manager is told what exceeds the new plan, in one notice, with the way to change plan', function () use ($base) {
    portalWithMuchBuilt($this->tenant);
    $this->reply = afterPlanChange($base);

    $this->license->refresh($this->tenant);

    $notice = collect(LayoutNotices::for($this->manager->fresh()))->firstWhere('key', 'over-plan');

    expect($notice['tone'])->toBe('amber')
        ->and($notice['message'])->toContain('Services: 7 of 3')
        ->and($notice['message'])->toContain('Operators: 3 of 1')
        ->and($notice['message'])->toContain('Shared resources: no longer included')
        ->and($notice['message'])->toContain('Group services: no longer included')
        ->and($notice['message'])->toContain('Service packages: no longer included')
        ->and($notice['message'])->toContain('Waiting list: no longer included')
        ->and($notice['message'])->toContain('nothing is deleted or hidden')
        ->and($notice['link']['href'])->toEndWith('/app/portal#your-plan');

    $this->actingAs($this->manager->fresh())->get(route('app.services'))->assertInertia(fn (Assert $page) => $page->where('notices', fn ($notices) => collect($notices)->contains('key', 'over-plan')));
});

test('the administrator of the platform reads it in the portal detail', function () use ($base) {
    portalWithMuchBuilt($this->tenant);
    $this->reply = afterPlanChange($base);

    $this->license->refresh($this->tenant);

    $this->actingAs($this->admin)
        ->get(route('admin.portals.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('plan.over_plan', fn ($list) => collect($list)->contains('Services: 7 of 3')));
});

test('a portal within its plan, or on a bigger one, gets no notice', function () use ($base) {
    Service::factory()->for($this->tenant)->count(2)->create();
    $this->reply = afterPlanChange($base);

    $this->license->refresh($this->tenant);

    expect(collect(LayoutNotices::for($this->manager->fresh()))->firstWhere('key', 'over-plan'))->toBeNull();

    // Moved up again to unlimited: whatever the portal has fits.
    portalWithMuchBuilt($this->tenant);
    $this->reply = afterPlanChange(['max_servizi' => null, 'max_operatori' => null, 'is_risorse' => true, 'is_lista_attesa' => true, 'is_pacchetti' => true, 'is_servizi_gruppo' => true, 'max_lista_attesa' => null, 'max_pacchetti' => null], 'studio', 'Studio');
    $this->travel(2)->minutes();
    $this->license->refresh($this->tenant->fresh());

    expect(collect(LayoutNotices::for($this->manager->fresh()))->firstWhere('key', 'over-plan'))->toBeNull();
});

test('the manager can ask again for the license, skipping the cache, to see the change at once', function () use ($base) {
    TenantLicense::factory()->for($this->tenant)->create(['plan_code' => 'pro', 'plan_name' => 'Pro', 'plan_attributes' => ['max_servizi' => 15]]);
    $this->reply = afterPlanChange($base);

    $this->actingAs($this->manager)->post(route('license.recheck'))->assertRedirect(route('dashboard'));

    expect($this->tenant->fresh()->license->plan_code)->toBe('base')
        ->and($this->tenant->fresh()->planUsage()->serviceLimit())->toBe(3);
});
