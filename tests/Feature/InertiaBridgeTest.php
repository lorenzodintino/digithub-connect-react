<?php

use App\Enums\BookingStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Fortify\Features;

/**
 * The headers the Inertia client sends on a visit from a React page.
 *
 * @return array<string, string>
 */
function inertiaVisit(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
    ];
}

test('a page visit that ends on an error page becomes a full visit', function () {
    $manager = User::factory()->manager(Tenant::factory()->create())->create();

    // The error pages are plain Blade: the browser loads the same address in
    // full and draws the 403, instead of Inertia's error window.
    $response = $this->actingAs($manager)->withHeaders(inertiaVisit())->get(route('admin.portals', ['lang' => 'it']));

    $response->assertConflict()
        ->assertHeader('X-Inertia-Location', route('admin.portals', ['lang' => 'it']));

    $this->withHeaders(inertiaVisit())->get('/una-pagina-che-non-esiste')
        ->assertConflict()
        ->assertHeader('X-Inertia-Location', url('/una-pagina-che-non-esiste'));

    // Loaded in full, the error page is the Blade one, in Italian: the visit above asked for it.
    $this->flushHeaders()->get(route('admin.portals'))->assertForbidden()->assertSee('Questa pagina non è per il tuo account');
});

test('an action refused during an Inertia visit goes back with a toast', function () {
    $tenant = Tenant::factory()->create();
    $operator = User::factory()->operator($tenant)->create();
    $colleague = User::factory()->operator($tenant)->create();
    $service = Service::factory()->for($tenant)->create();
    $booking = Booking::factory()->forSlot($service, $colleague, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create(['status' => BookingStatus::Pending, 'confirmed_at' => null]);

    $this->actingAs($operator)
        ->from(route('app.bookings'))
        ->withHeaders(inertiaVisit())
        ->post(route('app.bookings.confirm', $booking))
        ->assertRedirect(route('app.bookings'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Your account cannot do this.']);

    expect($booking->fresh()->status)->toBe(BookingStatus::Pending);
});

test('outside Inertia an error is left to Laravel', function () {
    $tenant = Tenant::factory()->create();
    $operator = User::factory()->operator($tenant)->create();
    $booking = Booking::factory()->forSlot(Service::factory()->for($tenant)->create(), User::factory()->operator($tenant)->create(), CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create(['status' => BookingStatus::Pending, 'confirmed_at' => null]);

    $this->actingAs($operator)->post(route('app.bookings.confirm', $booking))->assertForbidden();
    $this->actingAs($operator)->postJson(route('app.bookings.confirm', $booking))->assertForbidden();
});

test('a visit to a React page answers with the page object', function () {
    $manager = User::factory()->manager(Tenant::factory()->create())->create();

    $response = $this->actingAs($manager)->withHeaders(inertiaVisit())->get(route('dashboard'));

    $response->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'dashboard');
});

test('a redirect reaches the Inertia client untouched', function () {
    $response = $this->withHeaders(inertiaVisit())->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('a response that is not a page reaches the Inertia client untouched', function () {
    config(['fortify.features' => [...config('fortify.features'), Features::passkeys(['confirmPassword' => true])]]);

    $response = $this->withHeaders(inertiaVisit())->get(route('well-known.passkeys'));

    $response->assertOk()->assertJsonPath('enroll', route('security.edit'));
});
