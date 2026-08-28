<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Booking, Service and Tenant are bound by uid or slug, never by id. Passing an
 * id from a template silently resolves to nothing and throws a 404, and a test
 * that hands over the model itself cannot catch it: these call the components
 * exactly like the markup does.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'studio-rossi']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();
    $this->admin = User::factory()->admin()->create();
});

test('the agenda confirms a booking addressed by uid', function () {
    $service = Service::factory()->for($this->tenant)->create(['requires_confirmation' => true]);

    $booking = Booking::factory()
        ->forSlot($service, $this->operator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->pending()
        ->create();

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('confirm', $booking->uid);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('the service list edits and toggles a service addressed by slug', function () {
    $service = Service::factory()->for($this->tenant)->create(['slug' => 'consulenza', 'is_active' => true]);

    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('editService', $service->slug)
        ->assertSet('form.name', $service->name)
        ->call('toggleActive', $service->slug);

    expect($service->fresh()->is_active)->toBeFalse();
});

test('the portal list edits and toggles a portal addressed by slug', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.portals')
        ->call('editPortal', $this->tenant->slug)
        ->assertSet('form.name', $this->tenant->name)
        ->call('toggleActive', $this->tenant->slug);

    expect($this->tenant->fresh()->is_active)->toBeFalse();
});
