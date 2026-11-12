<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Ramsey\Uuid\Uuid;

/**
 * The identifier a portal has for the License Manager: an UUID born with the
 * portal that never changes and never repeats, whatever happens to the
 * numeric id (a recreated database starts it again from 1).
 */
afterEach(fn () => Str::createUuidsNormally());

test('every portal is born with its own lowercase UUID', function () {
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    expect($first->license_id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($second->license_id)->not->toBe($first->license_id)
        ->and($first->fresh()->license_id)->toBe($first->license_id);
});

test('the demo portal of the seeder has one too, although the seeder runs without model events', function () {
    $this->seed();

    expect(Tenant::where('slug', 'studio-rossi')->value('license_id'))
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

test('a portal created from the administrator form has one too', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.portals.store'), portalForm())
        ->assertSessionHasNoErrors();

    expect(Tenant::query()->where('slug', 'studio-bianchi')->sole()->license_id)->toHaveLength(36);
});

test('the identifier cannot be changed once the portal exists', function () {
    $tenant = Tenant::factory()->create();
    $original = $tenant->license_id;

    expect(fn () => $tenant->forceFill(['license_id' => (string) Str::uuid()])->save())->toThrow(LogicException::class, 'The license id of a portal never changes.');
    expect($tenant->fresh()->license_id)->toBe($original);

    // Saving anything else leaves it alone.
    $tenant->fresh()->update(['name' => 'Altro nome']);
    expect($tenant->fresh()->license_id)->toBe($original);
});

test('it cannot be set through mass assignment', function () {
    $tenant = Tenant::factory()->create();
    $original = $tenant->license_id;

    $tenant->update(['license_id' => (string) Str::uuid(), 'name' => 'Nuovo nome']);

    expect($tenant->fresh()->license_id)->toBe($original)->and($tenant->fresh()->name)->toBe('Nuovo nome');
});

test('the database itself refuses a repeated identifier', function () {
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    expect(fn () => DB::table('tenants')->where('id', $second->id)->update(['license_id' => $first->license_id]))->toThrow(QueryException::class);
});

test('a generated UUID that is already taken is thrown away and another one is used', function () {
    $existing = Tenant::factory()->create();
    $fresh = Str::uuid();
    Str::createUuidsUsingSequence([Uuid::fromString($existing->license_id), $fresh]);

    $new = Tenant::factory()->create();

    expect($new->license_id)->toBe((string) $fresh)->and($new->license_id)->not->toBe($existing->license_id);
});

test('after three taken UUIDs in a row it gives up instead of guessing', function () {
    $existing = Tenant::factory()->create();
    $taken = Uuid::fromString($existing->license_id);
    Str::createUuidsUsingSequence([$taken, $taken, $taken]);

    expect(fn () => Tenant::factory()->create())->toThrow(RuntimeException::class, 'Could not find an unused license id.');
});

test('the administrator list carries the identifier and finds a portal by it', function () {
    $tenant = Tenant::factory()->create(['name' => 'Studio Cercato']);
    Tenant::factory()->create(['name' => 'Altro portale']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.portals', ['q' => substr($tenant->license_id, 0, 13)]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('portals.data', 1)
            ->where('portals.data.0.name', 'Studio Cercato')
            ->where('portals.data.0.license_id', $tenant->license_id));
});
