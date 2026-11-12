<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

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

    $this->actingAs($this->manager)
        ->post('/app/bookings/'.$booking->uid.'/confirm')
        ->assertSessionHasNoErrors();

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('the service list edits and toggles a service addressed by slug', function () {
    $service = Service::factory()->for($this->tenant)->create(['slug' => 'consulenza', 'is_active' => true]);

    $this->actingAs($this->manager)
        ->get(route('app.services', ['edit' => $service->slug]))
        ->assertInertia(fn (Assert $page) => $page->where('editing.form.name', $service->name));

    $this->patch(route('app.services.toggle', $service->slug));

    expect($service->fresh()->is_active)->toBeFalse();
});

test('the portal list edits and toggles a portal addressed by slug', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.portals.show', 'studio-rossi'))
        ->assertInertia(fn (Assert $page) => $page->where('portal.name', $this->tenant->name));

    $this->patch('/admin/portals/studio-rossi/active');

    expect($this->tenant->fresh()->is_active)->toBeFalse();
});
