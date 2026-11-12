<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->tenant = Tenant::factory()->create();
    $this->operator = User::factory()->operator($this->tenant)->create();
});

test('an administrator deletes an account with nothing booked', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users', ['q' => $this->operator->email]))
        ->assertInertia(fn (Assert $page) => $page->where('accounts.data.0.upcoming_count', 0));

    $this->delete(route('admin.users.destroy', $this->operator))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Account deleted.');

    expect(User::query()->whereKey($this->operator->id)->exists())->toBeFalse();
});

test('an account holding upcoming bookings cannot be deleted from the admin panel', function () {
    $service = Service::factory()->for($this->tenant)->create();

    Booking::factory()
        ->forSlot($service, $this->operator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users', ['q' => $this->operator->email]))
        ->assertInertia(fn (Assert $page) => $page->where('accounts.data.0.upcoming_count', 1));

    $this->delete(route('admin.users.destroy', $this->operator))
        ->assertSessionHasErrors(['account' => 'This account still has one upcoming booking. Cancel or reassign it first.']);

    expect(User::query()->whereKey($this->operator->id)->exists())->toBeTrue()
        ->and(Booking::query()->count())->toBe(1);
});
