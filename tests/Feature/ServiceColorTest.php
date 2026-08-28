<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['brand_color' => '#ff00f7']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();
});

test('a new service follows the portal brand color', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('createService')
        ->set('form.name', 'Prima consulenza')
        ->set('form.operator_ids', [$this->operator->id])
        ->call('saveService')
        ->assertHasNoErrors();

    $service = Service::query()->sole();

    expect($service->color)->toBeNull()
        ->and($service->displayColor())->toBe('#ff00f7');

    $this->get(route('portal.show', $this->tenant))
        ->assertSee('top-0 h-1" style="background-color: #ff00f7', escape: false);
});

test('a service can keep its own color instead of the brand one', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('createService')
        ->set('form.name', 'Consulenza fiscale')
        ->set('form.use_brand_color', false)
        ->set('form.color', '#0f766e')
        ->set('form.operator_ids', [$this->operator->id])
        ->call('saveService')
        ->assertHasNoErrors();

    $service = Service::query()->sole();

    expect($service->color)->toBe('#0f766e')
        ->and($service->displayColor())->toBe('#0f766e');

    // Anchored on the card's colour bar: the brand also paints the logo, so a
    // bare colour string would match that instead.
    $this->get(route('portal.show', $this->tenant))
        ->assertSee('top-0 h-1" style="background-color: #0f766e', escape: false)
        ->assertDontSee('top-0 h-1" style="background-color: #ff00f7', escape: false);
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
        ->assertSee('top-0 h-1" style="background-color: #123456', escape: false)
        ->assertSee('top-0 h-1" style="background-color: #0f766e', escape: false);
});

test('the edit form reflects whether the service has its own color', function () {
    $following = Service::factory()->for($this->tenant)->followingBrandColor()->create();
    $own = Service::factory()->for($this->tenant)->create(['color' => '#0f766e']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('editService', $following->slug)
        ->assertSet('form.use_brand_color', true)
        ->assertSet('form.color', '#ff00f7')
        ->call('editService', $own->slug)
        ->assertSet('form.use_brand_color', false)
        ->assertSet('form.color', '#0f766e');
});

test('a service with its own color needs a valid hex value', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('createService')
        ->set('form.name', 'Apertura partita IVA')
        ->set('form.use_brand_color', false)
        ->set('form.color', 'verde')
        ->call('saveService')
        ->assertHasErrors(['form.color']);
});
