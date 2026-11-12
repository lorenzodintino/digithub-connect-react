<?php

use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->manager = User::factory()->manager($this->tenant)->create();
});

test('the services page lists the services of the portal in their order', function () {
    Service::factory()->for($this->tenant)->create(['name' => 'Consulenza', 'position' => 1]);
    Service::factory()->for($this->tenant)->create(['name' => 'Apertura', 'position' => 0]);
    Service::factory()->create(['name' => 'Altro portale']);

    $this->actingAs($this->manager)->get(route('app.services'))->assertInertia(fn (Assert $page) => $page
        ->component('app/services')
        ->has('services', 2)
        ->where('services.0.name', 'Apertura')
        ->where('services.1.name', 'Consulenza')
        ->where('editing', null));
});

test('a manager creates a service scoped to their own portal', function () {
    $operator = User::factory()->operator($this->tenant)->create();

    $this->actingAs($this->manager)
        ->post(route('app.services.store'), serviceForm($this->tenant, [
            'name' => 'Consulenza fiscale',
            'duration_minutes' => 45,
            'price' => '80',
            'location_type' => LocationType::Video->value,
            'operator_ids' => [$operator->id],
        ]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Service created.');

    $service = Service::query()->sole();

    expect($service->tenant_id)->toBe($this->tenant->id)
        ->and($service->slug)->toBe('consulenza-fiscale')
        ->and($service->duration_minutes)->toBe(45)
        ->and($service->price_cents)->toBe(8000)
        ->and($service->location_type)->toBe(LocationType::Video)
        ->and($service->operators->pluck('id')->all())->toBe([$operator->id]);
});

test('a service name is required and the duration has a floor', function () {
    $this->actingAs($this->manager)
        ->post(route('app.services.store'), serviceForm($this->tenant, ['name' => '', 'duration_minutes' => 1]))
        ->assertSessionHasErrors(['name', 'duration_minutes']);

    $this->assertDatabaseCount('services', 0);
});

test('the form opens on a service with its current values', function () {
    $service = Service::factory()->for($this->tenant)->create(['name' => 'Vecchio nome']);

    $this->actingAs($this->manager)
        ->get(route('app.services', ['edit' => $service->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('editing.slug', $service->slug)
            ->where('editing.form.name', 'Vecchio nome'));
});

test('a manager updates and deletes their own service', function () {
    $service = Service::factory()->for($this->tenant)->create(['name' => 'Vecchio nome']);

    $this->actingAs($this->manager)
        ->put(route('app.services.update', $service->slug), serviceForm($service, ['name' => 'Nuovo nome']))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Service updated.');

    expect($service->fresh()->name)->toBe('Nuovo nome');

    $this->delete(route('app.services.destroy', $service->slug))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Service deleted.');

    $this->assertDatabaseMissing('services', ['id' => $service->id]);
});

test('a manager cannot touch a service owned by another portal', function () {
    $foreignService = Service::factory()->create(['name' => 'Altrui']);

    $this->actingAs($this->manager);

    $this->get(route('app.services', ['edit' => $foreignService->slug]))->assertNotFound();
    $this->put(route('app.services.update', $foreignService->slug), serviceForm($foreignService, ['name' => 'Preso']))->assertNotFound();
    $this->patch(route('app.services.toggle', $foreignService->slug))->assertNotFound();
    $this->delete(route('app.services.destroy', $foreignService->slug))->assertNotFound();

    expect($foreignService->fresh()->name)->toBe('Altrui')
        ->and($foreignService->fresh()->is_active)->toBeTrue();
});

test('a service cannot be assigned to staff of another portal', function () {
    $own = User::factory()->operator($this->tenant)->create();
    $foreign = User::factory()->operator(Tenant::factory()->create())->create();
    $service = Service::factory()->for($this->tenant)->create();

    $this->actingAs($this->manager);

    $this->post(route('app.services.store'), serviceForm($this->tenant, ['name' => 'Nuovo', 'operator_ids' => [$own->id, $foreign->id]]))
        ->assertSessionHasErrors('operator_ids.1');
    $this->put(route('app.services.update', $service->slug), serviceForm($service, ['operator_ids' => [$foreign->id]]))
        ->assertSessionHasErrors('operator_ids.0');

    $this->assertDatabaseCount('services', 1);
    expect($service->operators()->count())->toBe(0);
});

test('two portals may share a service slug and each manager reaches their own', function () {
    $other = Tenant::factory()->create();
    $theirs = Service::factory()->for($other)->create(['slug' => 'consulenza', 'name' => 'Loro']);
    $ours = Service::factory()->for($this->tenant)->create(['slug' => 'consulenza', 'name' => 'Nostra']);

    $this->actingAs($this->manager)
        ->put(route('app.services.update', 'consulenza'), serviceForm($ours, ['name' => 'Nostra rinnovata']))
        ->assertSessionHasNoErrors();

    expect($ours->fresh()->name)->toBe('Nostra rinnovata')
        ->and($theirs->fresh()->name)->toBe('Loro');
});

test('publishing a service is toggled from the list', function () {
    $service = Service::factory()->for($this->tenant)->create(['is_active' => true]);

    $this->actingAs($this->manager)->patch(route('app.services.toggle', $service->slug));

    expect($service->fresh()->is_active)->toBeFalse();
});

test('a service holding upcoming bookings cannot be deleted', function () {
    $service = Service::factory()->for($this->tenant)->create();
    $operator = User::factory()->operator($this->tenant)->create();

    Booking::factory()
        ->forSlot($service, $operator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create();

    $this->actingAs($this->manager)
        ->get(route('app.services'))
        ->assertInertia(fn (Assert $page) => $page->where('services.0.upcoming_count', 1));

    $this->delete(route('app.services.destroy', $service->slug))
        ->assertSessionHasErrors(['service' => 'This service still has one upcoming booking. Cancel it first.']);

    expect(Service::query()->whereKey($service->id)->exists())->toBeTrue()
        ->and(Booking::query()->count())->toBe(1);
});

test('a service whose bookings are all past or cancelled can be deleted', function () {
    $service = Service::factory()->for($this->tenant)->create();
    $operator = User::factory()->operator($this->tenant)->create();

    Booking::factory()
        ->forSlot($service, $operator, CarbonImmutable::now('UTC')->subDays(3)->setTime(10, 0))
        ->create();

    Booking::factory()
        ->forSlot($service, $operator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create(['status' => BookingStatus::Cancelled]);

    $this->actingAs($this->manager)
        ->get(route('app.services'))
        ->assertInertia(fn (Assert $page) => $page->where('services.0.upcoming_count', 0));

    $this->delete(route('app.services.destroy', $service->slug))->assertSessionHasNoErrors();

    expect(Service::query()->whereKey($service->id)->exists())->toBeFalse();
});

test('a manager drags a service to another place in the list', function () {
    Service::factory()->for($this->tenant)->create(['name' => 'Primo', 'position' => 0]);
    Service::factory()->for($this->tenant)->create(['name' => 'Secondo', 'position' => 1]);
    $third = Service::factory()->for($this->tenant)->create(['name' => 'Terzo', 'position' => 2]);

    $this->actingAs($this->manager)->patch(route('app.services.reorder', $third->slug), ['position' => 0]);

    expect(Service::query()->orderBy('position')->pluck('name')->all())
        ->toBe(['Terzo', 'Primo', 'Secondo']);
});

test('the public page follows the order the portal chose', function () {
    $operator = User::factory()->operator($this->tenant)->create();

    foreach (['Primo', 'Secondo'] as $index => $name) {
        $service = Service::factory()->for($this->tenant)->create(['name' => $name, 'position' => $index]);
        $service->operators()->attach($operator);
    }

    $second = Service::query()->where('name', 'Secondo')->sole();

    $this->actingAs($this->manager)->patch(route('app.services.reorder', $second->slug), ['position' => 0]);
    auth()->logout();

    $html = $this->get(route('portal.show', $this->tenant))->assertOk()->getContent();

    expect(strpos($html, 'Secondo'))->toBeLessThan(strpos($html, 'Primo'));
});

test('a service of another portal cannot be dragged around', function () {
    $stranger = Service::factory()->create();

    $this->actingAs($this->manager)
        ->patch(route('app.services.reorder', $stranger->slug), ['position' => 0])
        ->assertNotFound();

    expect($stranger->fresh()->position)->toBe(0);
});
