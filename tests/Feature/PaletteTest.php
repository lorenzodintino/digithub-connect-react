<?php

use App\Enums\Palette;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Studio Rossi', 'palette' => Palette::Lampone]);
    $this->manager = User::factory()->manager($this->tenant)->create();
});

/**
 * The form of the portal settings with every required field filled.
 *
 * @return array<string, mixed>
 */
function portalSettings(Tenant $tenant, array $changes = []): array
{
    return [
        'name' => $tenant->name,
        'brand_color' => $tenant->brand_color,
        'palette' => $tenant->palette->value,
        'timezone' => $tenant->timezone,
        'locale' => $tenant->locale,
        ...$changes,
    ];
}

test('a new portal looks like the app always did: Basic', function () {
    $portal = Tenant::factory()->create();

    expect($portal->fresh()->palette)->toBe(Palette::Basic);
});

test('the manager chooses the palette of the business', function () {
    $this->actingAs($this->manager)
        ->put(route('app.portal.update'), portalSettings($this->tenant, ['palette' => 'oltremare']))
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->palette)->toBe(Palette::Oltremare);
});

test('only the known palettes are accepted', function () {
    $this->actingAs($this->manager)
        ->put(route('app.portal.update'), portalSettings($this->tenant, ['palette' => 'neon']))
        ->assertSessionHasErrors('palette');

    expect($this->tenant->fresh()->palette)->toBe(Palette::Lampone);
});

test('an operator cannot change the palette', function () {
    $operator = User::factory()->operator($this->tenant)->create();

    $this->actingAs($operator)
        ->put(route('app.portal.update'), portalSettings($this->tenant, ['palette' => 'basic']))
        ->assertForbidden();

    expect($this->tenant->fresh()->palette)->toBe(Palette::Lampone);
});

test('the portal settings offer the three palettes and the one in use', function () {
    $this->actingAs($this->manager)
        ->get(route('app.portal'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('portal.palette', 'lampone')
            ->has('palettes', 3)
            ->where('palettes.0.value', 'basic'));
});

test('the back office wears the palette of its business', function () {
    $this->actingAs($this->manager)
        ->get(route('app.portal'))
        ->assertInertia(fn (Assert $page) => $page->where('palette', 'lampone'));
});

test('the public pages wear the palette of the portal, for anyone', function () {
    $this->get(route('portal.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('palette', 'lampone'));
});

test('the page is drawn in the palette from the very first byte', function () {
    $this->get(route('portal.show', $this->tenant))->assertSee("document.documentElement.dataset.palette = 'lampone'", false);
});

test('a form from before the palettes existed still saves and keeps the palette', function () {
    $this->actingAs($this->manager)
        ->put(route('app.portal.update'), Arr::except(portalSettings($this->tenant), 'palette'))
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->palette)->toBe(Palette::Lampone);
});

test('pages that belong to nobody have no palette and the home page keeps the visitor choice', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('palette', null));

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page->component('welcome')->where('palette', null))
        ->assertDontSee("dataset.palette = '", false);
});

test('an administrator without a business sees Basic', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('palette', null));
});

test('the manager finds the palette under Settings › Appearance and saves it there', function () {
    $this->actingAs($this->manager)
        ->get(route('appearance.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('palette', 'lampone')
            ->where('paletteChoice.current', 'lampone')
            ->where('paletteChoice.editable', true)
            ->has('paletteChoice.options', 3));

    $this->put(route('appearance.update'), ['palette' => 'oltremare'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Palette saved.');

    expect($this->tenant->fresh()->palette)->toBe(Palette::Oltremare);
});

test('Appearance refuses an unknown palette', function () {
    $this->actingAs($this->manager)
        ->put(route('appearance.update'), ['palette' => 'neon'])
        ->assertSessionHasErrors('palette');
});

test('an operator sees the palette in Appearance but cannot change it', function () {
    $operator = User::factory()->operator($this->tenant)->create();

    $this->actingAs($operator)
        ->get(route('appearance.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('paletteChoice.editable', false)->where('paletteChoice.label', 'Berry'));

    $this->put(route('appearance.update'), ['palette' => 'basic'])->assertForbidden();

    expect($this->tenant->fresh()->palette)->toBe(Palette::Lampone);
});

test('the administrator picks a palette for themselves and only they wear it', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('appearance.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('paletteChoice.scope', 'personal')
            ->where('paletteChoice.editable', true)
            ->where('paletteChoice.current', 'basic'));

    $this->put(route('appearance.update'), ['palette' => 'oltremare'])->assertSessionHasNoErrors();

    expect($admin->fresh()->palette)->toBe(Palette::Oltremare)
        ->and($this->tenant->fresh()->palette)->toBe(Palette::Lampone);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('palette', 'oltremare'));
});

test('the business palette wins over the administrator one on its own pages', function () {
    $admin = User::factory()->admin()->create(['palette' => Palette::Oltremare]);

    $this->actingAs($admin)
        ->get(route('portal.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('palette', 'lampone'));
});

test('a manager has no personal palette: the business one is the only one', function () {
    $this->actingAs($this->manager)->put(route('appearance.update'), ['palette' => 'oltremare']);

    expect($this->manager->fresh()->palette)->toBeNull();
});
