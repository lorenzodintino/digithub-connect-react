<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->tenant = Tenant::factory()->create();
    $this->operator = User::factory()->operator($this->tenant)->create();
});

test('an administrator deletes an account with nothing booked', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('confirmDelete', $this->operator->id)
        ->assertSet('deletingUpcomingCount', 0)
        ->call('deleteAccount');

    expect(User::query()->whereKey($this->operator->id)->exists())->toBeFalse();
});

test('an account holding upcoming bookings cannot be deleted from the admin panel', function () {
    $service = Service::factory()->for($this->tenant)->create();

    Booking::factory()
        ->forSlot($service, $this->operator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.users')
        ->call('confirmDelete', $this->operator->id)
        ->assertSet('deletingUpcomingCount', 1)
        ->call('deleteAccount');

    expect(User::query()->whereKey($this->operator->id)->exists())->toBeTrue()
        ->and(Booking::query()->count())->toBe(1);
});
