<?php

use App\Actions\Plans\SyncPlanCatalogue;
use App\Mail\Platform\LicenseManagerDownMail;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The plans are defined in the License Manager: the table is its copy, kept by code. The attributes
 * have the License Manager's own keys (the Italian ones), written in one place: App\Support\PlanAttributes.
 */
beforeEach(function () {
    Mail::fake();
    config(['services.license.enforcement' => 'on']);

    $this->reply = fn () => Http::response(atrioCatalogue());
    Http::fake(['license.test/api/v1/plans*' => fn () => ($this->reply)()]);

    $this->admin = User::factory()->admin()->create();
});

/**
 * One plan of the catalogue as the License Manager sends it.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function catalogueEntry(string $code, array $changes = []): array
{
    return [
        'id' => 'cm_'.$code,
        'code' => $code,
        'name' => ucfirst($code),
        'description' => 'Piano '.$code,
        'position' => 1,
        'active' => true,
        'prices' => [
            ['months' => 12, 'priceCents' => 19000, 'currency' => 'EUR', 'active' => true],
            ['months' => 1, 'priceCents' => 1900, 'currency' => 'EUR', 'active' => true],
        ],
        'attributes' => [
            'max_servizi' => 3, 'max_operatori' => 1, 'max_prenotazioni_mese' => 100, 'max_lista_attesa' => 0, 'max_pacchetti' => 0,
            'is_risorse' => false, 'is_lista_attesa' => false, 'is_pacchetti' => false, 'is_servizi_gruppo' => false,
            'is_pulsanti_calendario' => false, 'is_embed_link' => false, 'is_custom_smtp' => false,
        ],
        ...$changes,
    ];
}

/**
 * The three plans of Atrio, as the License Manager answers.
 *
 * @return array<string, mixed>
 */
function atrioCatalogue(): array
{
    return ['data' => [
        'software' => ['code' => 'ATRIO', 'name' => 'Atrio'],
        'updatedAt' => now()->toIso8601String(),
        'attributes' => [],
        'plans' => [
            catalogueEntry('base'),
            catalogueEntry('pro', ['position' => 2, 'prices' => [['months' => 1, 'priceCents' => 3900, 'currency' => 'EUR', 'active' => true]], 'attributes' => [
                'max_servizi' => 15, 'max_operatori' => 5, 'max_prenotazioni_mese' => 1000, 'max_lista_attesa' => 5, 'max_pacchetti' => 3,
                'is_risorse' => true, 'is_lista_attesa' => true, 'is_pacchetti' => true, 'is_servizi_gruppo' => false,
                'is_pulsanti_calendario' => true, 'is_embed_link' => true, 'is_custom_smtp' => true,
            ]]),
            catalogueEntry('studio', ['position' => 3, 'prices' => [['months' => 1, 'priceCents' => 7900, 'currency' => 'EUR', 'active' => true]], 'attributes' => [
                'max_servizi' => null, 'max_operatori' => null, 'max_prenotazioni_mese' => null, 'max_lista_attesa' => null, 'max_pacchetti' => null,
                'is_risorse' => true, 'is_lista_attesa' => true, 'is_pacchetti' => true, 'is_servizi_gruppo' => true,
                'is_pulsanti_calendario' => true, 'is_embed_link' => true, 'is_custom_smtp' => true,
            ]]),
        ],
    ]];
}

test('the catalogue becomes the plans of the platform, by code, with the keys of the License Manager', function () {
    $this->reply = fn () => Http::response(atrioCatalogue(), 200, ['ETag' => '"v1"']);

    $result = app(SyncPlanCatalogue::class)->handle();

    expect($result)->toMatchArray(['status' => 'updated', 'created' => 3, 'updated' => 0, 'retired' => 0]);

    $pro = Plan::query()->where('code', 'pro')->sole();

    expect($pro->name)->toBe('Pro')
        ->and($pro->description)->toBe('Piano pro')
        ->and($pro->position)->toBe(2)
        ->and($pro->price_cents)->toBe(3900)
        ->and($pro->is_active)->toBeTrue()
        ->and($pro->max_services)->toBe(15)
        ->and($pro->max_operators)->toBe(5)
        ->and($pro->max_bookings_per_month)->toBe(1000)
        ->and($pro->max_waiting_list)->toBe(5)
        ->and($pro->max_packages)->toBe(3)
        ->and($pro->featureMap())->toBe(['resources' => true, 'waiting_list' => true, 'packages' => true, 'group_services' => false, 'calendar_buttons' => true, 'embed' => true, 'custom_smtp' => true])
        ->and($pro->synced_at)->not->toBeNull();

    $studio = Plan::query()->where('code', 'studio')->sole();

    expect($studio->max_services)->toBeNull()->and($studio->max_packages)->toBeNull()->and($studio->allows('group_services'))->toBeTrue();

    $base = Plan::query()->where('code', 'base')->sole();

    // The monthly price is the one with one month, not the first of the list.
    expect($base->price_cents)->toBe(1900)->and($base->max_waiting_list)->toBe(0)->and($base->allows('resources'))->toBeFalse();
});

test('the request carries the key, asks for the software and nothing else', function () {

    app(SyncPlanCatalogue::class)->handle();

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'http://license.test/api/v1/plans?')
            && $query === ['software' => 'TEST_SOFTWARE']
            && $request->hasHeader('Authorization', 'Bearer lk_test_key')
            && ! str_contains($request->url(), 'lk_test_key');
    });
});

test('a plan already there is updated in place, with the portals on it', function () {
    $old = Plan::factory()->create(['code' => 'base', 'name' => 'Vecchio nome', 'position' => 9, 'max_services' => 99, 'is_active' => false]);
    $tenant = Tenant::factory()->create(['plan_id' => $old->id]);

    $result = app(SyncPlanCatalogue::class)->handle();

    expect($result)->toMatchArray(['created' => 2, 'updated' => 1])
        ->and($old->fresh()->name)->toBe('Base')
        ->and($old->fresh()->max_services)->toBe(3)
        ->and($old->fresh()->position)->toBe(1)
        ->and($old->fresh()->is_active)->toBeTrue()
        ->and($tenant->fresh()->plan_id)->toBe($old->id);
});

test('a plan that left the catalogue is taken off sale, never deleted, and a plan only defined here is left alone', function () {
    $gone = Plan::factory()->create(['code' => 'platino', 'name' => 'Platino']);
    $local = Plan::factory()->create(['code' => null, 'name' => 'Solo qui']);
    Tenant::factory()->create(['plan_id' => $gone->id]);

    $result = app(SyncPlanCatalogue::class)->handle();

    expect($result['retired'])->toBe(1)
        ->and($gone->fresh()->is_active)->toBeFalse()
        ->and($gone->fresh()->tenants()->count())->toBe(1)
        ->and($local->fresh()->is_active)->toBeTrue();
});

test('an attribute the catalogue leaves out is not included, and a plan can be on sale or not', function () {
    $entry = catalogueEntry('lite', ['active' => false, 'attributes' => ['max_servizi' => 2, 'is_risorse' => true]]);
    $this->reply = fn () => Http::response(['data' => ['plans' => [$entry]]]);

    app(SyncPlanCatalogue::class)->handle();

    $lite = Plan::query()->where('code', 'lite')->sole();

    expect($lite->is_active)->toBeFalse()
        ->and($lite->max_services)->toBe(2)
        ->and($lite->max_operators)->toBe(0)
        ->and($lite->max_packages)->toBe(0)
        ->and($lite->allows('resources'))->toBeTrue()
        ->and($lite->allows('embed'))->toBeFalse();
});

test('an unchanged catalogue answers 304: the ETag goes back and nothing changes', function () {
    $calls = 0;
    $this->reply = function () use (&$calls) {
        return ++$calls === 1 ? Http::response(atrioCatalogue(), 200, ['ETag' => '"v1"']) : Http::response('', 304);
    };

    app(SyncPlanCatalogue::class)->handle();
    $second = app(SyncPlanCatalogue::class)->handle();

    expect($second)->toMatchArray(['status' => 'unchanged', 'created' => 0, 'updated' => 0, 'retired' => 0]);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request->hasHeader('If-None-Match', '"v1"'));
});

test('the first read is whole even if an ETag is remembered, and --force reads it whole too', function () {
    Cache::forever('license.catalogue.etag', '"stale"');
    $this->reply = fn () => Http::response(atrioCatalogue(), 200, ['ETag' => '"v2"']);

    app(SyncPlanCatalogue::class)->handle();

    Http::assertSent(fn (Request $request) => ! $request->hasHeader('If-None-Match'));

    app(SyncPlanCatalogue::class)->handle(force: true);

    expect(Http::recorded())->toHaveCount(2);
    Http::assertSent(fn (Request $request) => ! $request->hasHeader('If-None-Match'));
});

test('a catalogue that changes is applied and an unchanged one leaves the register quiet', function () {

    app(SyncPlanCatalogue::class)->handle(force: true);
    app(SyncPlanCatalogue::class)->handle(force: true);

    expect(ActivityLog::query()->where('action', 'plan.catalogue_synced')->count())->toBe(1);

    $changed = atrioCatalogue();
    $changed['data']['plans'][1]['attributes']['max_servizi'] = 20;
    $this->reply = fn () => Http::response($changed);

    $result = app(SyncPlanCatalogue::class)->handle(force: true);

    expect($result)->toMatchArray(['status' => 'updated', 'updated' => 1])
        ->and(Plan::query()->where('code', 'pro')->sole()->max_services)->toBe(20)
        ->and(ActivityLog::query()->where('action', 'plan.catalogue_synced')->count())->toBe(2);
});

test('when the License Manager does not answer the last copy stays and the administrators are told', function () {
    app(SyncPlanCatalogue::class)->handle();

    $this->reply = fn () => throw new ConnectionException('cURL error 28');

    $result = app(SyncPlanCatalogue::class)->handle(force: true);

    expect($result)->toMatchArray(['status' => 'failed', 'error' => 'unreachable'])
        ->and(Plan::query()->count())->toBe(3)
        ->and(Plan::query()->where('code', 'pro')->sole()->max_services)->toBe(15);

    Mail::assertQueued(LicenseManagerDownMail::class, fn (LicenseManagerDownMail $mail) => $mail->hasTo($this->admin->email) && $mail->code === 'unreachable');
});

test('an answer that is not a catalogue changes nothing', function (mixed $body) {
    $this->reply = fn () => Http::response($body);

    $result = app(SyncPlanCatalogue::class)->handle();

    expect($result)->toMatchArray(['status' => 'failed', 'error' => 'malformed'])->and(Plan::query()->count())->toBe(0);
})->with([
    'not json' => ['<html>maintenance</html>'],
    'no plans' => [['data' => ['plans' => []]]],
    'plans without the contract shape' => [['data' => ['plans' => [['code' => 'Pro!', 'name' => '', 'position' => 'one']]]]],
]);

test('one badly shaped plan is skipped and the others are kept', function () {
    $catalogue = atrioCatalogue();
    $catalogue['data']['plans'][] = ['code' => 'x', 'name' => 'Rotto'];
    $this->reply = fn () => Http::response($catalogue);

    $result = app(SyncPlanCatalogue::class)->handle();

    expect($result['created'])->toBe(3)->and(Plan::query()->where('code', 'x')->exists())->toBeFalse();
});

test('a refusal is a failure with its code, and a rate limit is not an outage', function () {
    $this->reply = fn () => Http::response(['error' => ['code' => 'unauthorized', 'message' => 'x']], 401);

    expect(app(SyncPlanCatalogue::class)->handle())->toMatchArray(['status' => 'failed', 'error' => 'unauthorized']);
    Mail::assertQueued(LicenseManagerDownMail::class, fn (LicenseManagerDownMail $mail) => $mail->configuration);

    Cache::flush();
    Mail::fake();
    $this->reply = fn () => Http::response(['error' => ['code' => 'rate_limited', 'message' => 'x']], 429, ['Retry-After' => '30']);

    expect(app(SyncPlanCatalogue::class)->handle())->toMatchArray(['status' => 'failed', 'error' => 'rate_limited']);
    Mail::assertNotQueued(LicenseManagerDownMail::class);
});

test('the command syncs while enforcement is on, and does nothing while it is off', function () {

    config(['services.license.enforcement' => 'off']);
    $this->artisan('plans:sync')->expectsOutputToContain('nothing to sync')->assertSuccessful();
    Http::assertNothingSent();

    config(['services.license.enforcement' => 'on']);
    $this->artisan('plans:sync')->expectsOutputToContain('3 created')->assertSuccessful();

    expect(Plan::query()->count())->toBe(3);
});

test('the command fails loudly when the catalogue cannot be read', function () {
    $this->reply = fn () => throw new ConnectionException('cURL error 28');

    $this->artisan('plans:sync')->expectsOutputToContain('last copy stays in use')->assertFailed();
});

test('the command is on the schedule', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command);

    expect($events->contains(fn (string $command) => str_contains($command, 'plans:sync')))->toBeTrue();
});

test('with the License Manager in charge the plans page shows them and does not change them', function () {
    $plan = Plan::factory()->create(['code' => 'pro', 'name' => 'Pro']);

    $this->actingAs($this->admin)
        ->get(route('admin.plans'))
        ->assertInertia(fn (Assert $page) => $page->where('catalogue.managed', true)->where('catalogue.synced_at', null));

    $this->post(route('admin.plans.store'), planForm(['name' => 'Furbo']))->assertForbidden();
    $this->put(route('admin.plans.update', $plan), planForm(['name' => 'Cambiato']))->assertForbidden();
    $this->patch(route('admin.plans.toggle', $plan))->assertForbidden();
    $this->delete(route('admin.plans.destroy', $plan))->assertForbidden();

    expect($plan->fresh()->name)->toBe('Pro')->and($plan->fresh()->is_active)->toBeTrue()->and(Plan::query()->count())->toBe(1);
});

test('the plans page says when they were last aligned', function () {
    app(SyncPlanCatalogue::class)->handle();

    $this->actingAs($this->admin)
        ->get(route('admin.plans'))
        ->assertInertia(fn (Assert $page) => $page->where('catalogue.managed', true)->where('catalogue.synced_at', fn ($date) => is_string($date) && $date !== ''));
});

test('with enforcement off the plans are the local ones and can be changed here', function () {
    config(['services.license.enforcement' => 'off']);
    $plan = Plan::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.plans'))->assertInertia(fn (Assert $page) => $page->where('catalogue.managed', false));
    $this->patch(route('admin.plans.toggle', $plan))->assertSessionHasNoErrors();

    expect($plan->fresh()->is_active)->toBeFalse();
});
