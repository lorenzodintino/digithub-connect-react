<?php

use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->manager = User::factory()->manager($this->tenant)->create();
});

test('a manager creates a service scoped to their own portal', function () {
    $operator = User::factory()->operator($this->tenant)->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('createService')
        ->set('form.name', 'Consulenza fiscale')
        ->set('form.duration_minutes', 45)
        ->set('form.price', '80')
        ->set('form.location_type', LocationType::Video->value)
        ->set('form.operator_ids', [$operator->id])
        ->call('saveService')
        ->assertHasNoErrors();

    $service = Service::query()->sole();

    expect($service->tenant_id)->toBe($this->tenant->id)
        ->and($service->slug)->toBe('consulenza-fiscale')
        ->and($service->duration_minutes)->toBe(45)
        ->and($service->price_cents)->toBe(8000)
        ->and($service->location_type)->toBe(LocationType::Video)
        ->and($service->operators->pluck('id')->all())->toBe([$operator->id]);
});

test('a service name is required and the duration has a floor', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('createService')
        ->set('form.name', '')
        ->set('form.duration_minutes', 1)
        ->call('saveService')
        ->assertHasErrors(['form.name', 'form.duration_minutes']);
});

test('a manager updates and deletes their own service', function () {
    $service = Service::factory()->for($this->tenant)->create(['name' => 'Vecchio nome']);

    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('editService', $service)
        ->set('form.name', 'Nuovo nome')
        ->call('saveService')
        ->assertHasNoErrors();

    expect($service->fresh()->name)->toBe('Nuovo nome');

    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('confirmDelete', $service->id)
        ->call('deleteService');

    $this->assertDatabaseMissing('services', ['id' => $service->id]);
});

test('a manager cannot touch a service owned by another portal', function () {
    $foreignService = Service::factory()->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('editService', $foreignService)
        ->assertForbidden();
});

test('publishing a service is toggled from the list', function () {
    $service = Service::factory()->for($this->tenant)->create(['is_active' => true]);

    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('toggleActive', $service);

    expect($service->fresh()->is_active)->toBeFalse();
});

test('a service holding upcoming bookings cannot be deleted', function () {
    $service = Service::factory()->for($this->tenant)->create();
    $operator = User::factory()->operator($this->tenant)->create();

    Booking::factory()
        ->forSlot($service, $operator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('confirmDelete', $service->id)
        ->assertSet('deletingUpcomingCount', 1)
        ->call('deleteService');

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

    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('confirmDelete', $service->id)
        ->assertSet('deletingUpcomingCount', 0)
        ->call('deleteService');

    expect(Service::query()->whereKey($service->id)->exists())->toBeFalse();
});

test('the services page carries the help sheet with its examples', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->assertSee('How a service works')
        ->assertSee('Duration and slot interval')
        ->assertSee('Buffer before and buffer after')
        ->assertSee('Minimum notice')
        ->assertSee('Bookings need manual approval')
        // The worked examples are the point of the sheet, not just the titles.
        ->assertSee('09:45', escape: false)
        ->assertSee('service-help', escape: false);
});
