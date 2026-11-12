<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['brand_color' => '#ff00f7']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();
});

test('a new service follows the portal brand color', function () {
    $this->actingAs($this->manager)
        ->post(route('app.services.store'), serviceForm($this->tenant, [
            'name' => 'Prima consulenza',
            'operator_ids' => [$this->operator->id],
        ]))
        ->assertSessionHasNoErrors();

    $service = Service::query()->sole();

    expect($service->color)->toBeNull()
        ->and($service->displayColor())->toBe('#ff00f7');

    $this->get(route('portal.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('services.0.color', '#ff00f7'));
});

test('a service can keep its own color instead of the brand one', function () {
    $this->actingAs($this->manager)
        ->post(route('app.services.store'), serviceForm($this->tenant, [
            'name' => 'Consulenza fiscale',
            'use_brand_color' => false,
            'color' => '#0f766e',
            'operator_ids' => [$this->operator->id],
        ]))
        ->assertSessionHasNoErrors();

    $service = Service::query()->sole();

    expect($service->color)->toBe('#0f766e')
        ->and($service->displayColor())->toBe('#0f766e');

    $this->get(route('portal.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('services.0.color', '#0f766e')->where('portal.brand', '#ff00f7'));
});

test('changing the brand color repaints the services that follow it', function () {
    $following = Service::factory()->for($this->tenant)->followingBrandColor()->create();
    $own = Service::factory()->for($this->tenant)->create(['color' => '#0f766e']);

    $following->operators()->sync([$this->operator->id]);
    $own->operators()->sync([$this->operator->id]);

    $this->tenant->update(['brand_color' => '#123456']);

    expect($following->fresh()->displayColor())->toBe('#123456')
        ->and($own->fresh()->displayColor())->toBe('#0f766e');

    $this->get(route('portal.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('services', fn ($services) => collect($services)->pluck('color')->sort()->values()->all() === ['#0f766e', '#123456']));
});

test('the edit form reflects whether the service has its own color', function () {
    $following = Service::factory()->for($this->tenant)->followingBrandColor()->create();
    $own = Service::factory()->for($this->tenant)->create(['color' => '#0f766e']);

    $this->actingAs($this->manager);

    $this->get(route('app.services', ['edit' => $following->slug]))->assertInertia(fn (Assert $page) => $page
        ->where('editing.form.use_brand_color', true)
        ->where('editing.form.color', '#ff00f7'));

    $this->get(route('app.services', ['edit' => $own->slug]))->assertInertia(fn (Assert $page) => $page
        ->where('editing.form.use_brand_color', false)
        ->where('editing.form.color', '#0f766e'));
});

test('a service with its own color needs a valid hex value', function () {
    $this->actingAs($this->manager)
        ->post(route('app.services.store'), serviceForm($this->tenant, [
            'name' => 'Apertura partita IVA',
            'use_brand_color' => false,
            'color' => 'verde',
        ]))
        ->assertSessionHasErrors(['color']);
});
