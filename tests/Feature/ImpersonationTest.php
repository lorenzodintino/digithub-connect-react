<?php

use App\Models\ActivityLog;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Impersonation;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Platform Admin']);
    $this->tenant = Tenant::factory()->create(['name' => 'Studio Rossi']);
    $this->manager = User::factory()->manager($this->tenant)->create(['name' => 'Marco Rossi']);
    $this->operator = User::factory()->operator($this->tenant)->create();
});

test('an administrator steps into a portal as its manager', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.portals.impersonate', $this->tenant))
        ->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($this->manager->id)
        ->and(app(Impersonation::class)->isActive())->toBeTrue()
        ->and(app(Impersonation::class)->actor()->id)->toBe($this->admin->id);

    $log = ActivityLog::query()->where('action', 'impersonation.started')->sole();

    expect($log->actor_name)->toBe('Platform Admin')
        ->and($log->subject_label)->toBe('Marco Rossi')
        ->and($log->tenant_id)->toBe($this->tenant->id);
});

test('the banner is on every screen while it lasts', function () {
    $this->actingAs($this->admin);

    app(Impersonation::class)->start($this->admin, $this->manager);

    // A React screen receives it as a shared prop…
    $this->get(route('app.services'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notices.0.message', 'You are working as Marco Rossi.')
            ->where('notices.0.link.href', route('impersonation.stop')));

    // …and so does every other, not only the one the session started on.
    $this->get(route('app.bookings'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notices.0.message', 'You are working as Marco Rossi.')
            ->where('notices.0.link.href', route('impersonation.stop')));
});

test('the way back returns the administrator to their own account', function () {
    $this->actingAs($this->admin);

    app(Impersonation::class)->start($this->admin, $this->manager);

    $this->get(route('impersonation.stop'))->assertRedirect(route('admin.portals'));

    expect(auth()->id())->toBe($this->admin->id)
        ->and(app(Impersonation::class)->isActive())->toBeFalse()
        ->and(ActivityLog::query()->where('action', 'impersonation.stopped')->count())->toBe(1);
});

test('everything done meanwhile says who was really behind it', function () {
    $this->actingAs($this->admin);

    app(Impersonation::class)->start($this->admin, $this->manager);

    $service = Service::factory()->for($this->tenant)->create();

    $this->put(route('app.services.update', $service->slug), serviceForm($service, ['name' => 'Servizio corretto dal supporto']))
        ->assertSessionHasNoErrors();

    $log = ActivityLog::query()->where('action', 'service.updated')->sole();

    expect($log->user_id)->toBe($this->manager->id)
        ->and($log->context['impersonated_by'])->toBe('Platform Admin');
});

test('the session drops by itself once the time is up', function () {
    $this->actingAs($this->admin);

    app(Impersonation::class)->start($this->admin, $this->manager);

    $this->travel(Impersonation::MINUTES + 1)->minutes();

    $this->get(route('dashboard'))->assertOk();

    expect(auth()->id())->toBe($this->admin->id)
        ->and(app(Impersonation::class)->isActive())->toBeFalse()
        ->and(ActivityLog::query()->where('action', 'impersonation.expired')->count())->toBe(1);
});

test('an administrator cannot step into another administrator', function () {
    $other = User::factory()->admin()->create();

    $this->actingAs($this->admin);

    expect(fn () => app(Impersonation::class)->start($this->admin, $other))
        ->toThrow(HttpException::class);

    expect(auth()->id())->toBe($this->admin->id);
});

test('impersonation does not nest', function () {
    $this->actingAs($this->admin);

    app(Impersonation::class)->start($this->admin, $this->manager);

    expect(fn () => app(Impersonation::class)->start($this->admin, $this->operator))
        ->toThrow(HttpException::class);

    expect(auth()->id())->toBe($this->manager->id);
});

test('a manager cannot step into anybody', function () {
    $this->actingAs($this->manager);

    expect(fn () => app(Impersonation::class)->start($this->manager, $this->operator))
        ->toThrow(HttpException::class);

    // The admin screens are closed by the route, not by the component: that is
    // the door a manager would actually walk into.
    $this->get(route('admin.portals.show', $this->tenant))->assertForbidden();
});

test('a suspended account cannot be stepped into', function () {
    $this->manager->update(['is_active' => false]);

    $this->actingAs($this->admin);

    expect(fn () => app(Impersonation::class)->start($this->admin, $this->manager))
        ->toThrow(HttpException::class);
});
