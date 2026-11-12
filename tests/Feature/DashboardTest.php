<?php

use App\Models\AvailabilityRule;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Impersonation;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('an administrator sees the platform counters and the latest portals', function () {
    $active = Tenant::factory()->create(['name' => 'Studio Rossi', 'created_at' => now()->subDay()]);
    $suspended = Tenant::factory()->inactive()->create(['name' => 'Studio Bianchi']);
    User::factory()->manager($active)->create(['email' => 'marco@rossi.test']);
    User::factory()->operator($active)->count(2)->create();
    Service::factory()->for($active)->count(3)->create();

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('admin.stats', ['portals' => 2, 'active_portals' => 1, 'managers' => 1, 'operators' => 2])
        ->has('admin.recent_portals', 2)
        ->where('admin.recent_portals.0.name', 'Studio Bianchi')
        ->where('admin.recent_portals.0.is_active', false)
        ->where('admin.recent_portals.1.slug', $active->slug)
        ->where('admin.recent_portals.1.manager_email', 'marco@rossi.test')
        ->where('admin.recent_portals.1.services_count', 3)
        ->missing('manager')
        ->missing('operator'));
});

test('a manager sees the counters of their own portal only', function () {
    $tenant = Tenant::factory()->create();
    $manager = User::factory()->manager($tenant)->create();
    $operator = User::factory()->operator($tenant)->create();
    $service = Service::factory()->for($tenant)->create();
    Service::factory()->for($tenant)->inactive()->create();
    Booking::factory()->for($service)->create(['user_id' => $operator->id]);
    Booking::factory()->for($service)->pending()->create(['user_id' => $operator->id]);
    Booking::factory()->for($service)->cancelled()->create(['user_id' => $operator->id]);
    Booking::factory()->create();

    $response = $this->actingAs($manager)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('manager.stats', ['services' => 1, 'staff' => 1, 'upcoming' => 2, 'pending' => 1])
        ->where('auth.user.tenant.public_url', $tenant->publicUrl())
        ->missing('admin')
        ->missing('operator'));
});

test('an operator sees their own next bookings and weekly hours', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00', 'UTC'));
    $tenant = Tenant::factory()->create();
    $operator = User::factory()->operator($tenant)->create(['timezone' => 'Europe/Rome']);
    $colleague = User::factory()->operator($tenant)->create();
    $service = Service::factory()->for($tenant)->create(['name' => 'Prima consulenza']);
    $service->operators()->attach($operator);
    Booking::factory()->for($service)->create([
        'user_id' => $operator->id,
        'customer_name' => 'Anna Verdi',
        'starts_at' => CarbonImmutable::parse('2026-10-06 10:00', 'UTC'),
    ]);
    Booking::factory()->for($service)->cancelled()->create(['user_id' => $operator->id]);
    Booking::factory()->for($service)->create(['user_id' => $colleague->id]);
    AvailabilityRule::factory()->for($operator)->onWeekday(1)->between('09:00', '13:00')->create();

    $response = $this->actingAs($operator)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('operator.next_bookings', 1)
        ->where('operator.next_bookings.0.customer', 'Anna Verdi')
        ->where('operator.next_bookings.0.service', 'Prima consulenza')
        ->where('operator.next_bookings.0.day', 'Tue 06 Oct')
        ->where('operator.next_bookings.0.time', '12:00')
        ->where('operator.week', [['id' => AvailabilityRule::query()->sole()->id, 'day' => 'Monday', 'hours' => '09:00 – 13:00']])
        ->where('operator.services_count', 1)
        ->missing('admin')
        ->missing('manager'));
});

test('the shell receives the signed in person and the notices above the page', function () {
    $admin = User::factory()->admin()->create();
    $tenant = Tenant::factory()->create();
    $manager = User::factory()->manager($tenant)->create(['name' => 'Marco Rossi']);
    $this->actingAs($admin);
    app(Impersonation::class)->start($admin, $manager);

    $response = $this->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.name', 'Marco Rossi')
        ->where('auth.user.role', 'manager')
        ->where('locale', 'en')
        ->where('notices.0.key', 'impersonation')
        ->where('notices.0.message', 'You are working as Marco Rossi.')
        ->where('notices.0.link.href', route('impersonation.stop')));
});
