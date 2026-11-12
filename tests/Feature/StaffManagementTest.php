<?php

use App\Enums\UserRole;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->manager = User::factory()->manager($this->tenant)->create();
});

/**
 * Everything the staff form sends.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function staffForm(array $changes = []): array
{
    return [
        'name' => 'Giulia Bianchi',
        'email' => 'giulia@example.com',
        'phone' => '',
        'job_title' => '',
        'bio' => '',
        'timezone' => 'Europe/Rome',
        'locale' => 'it',
        'is_bookable' => true,
        'is_active' => true,
        'service_ids' => [],
        ...$changes,
    ];
}

test('a manager creates an operator inside their own portal', function () {
    $service = Service::factory()->for($this->tenant)->create();

    $response = $this->actingAs($this->manager)
        ->post(route('app.staff.store'), staffForm(['service_ids' => [$service->id]]));

    $response->assertSessionHasNoErrors()
        ->assertInertiaFlash('credentials.email', 'giulia@example.com');

    $operator = User::query()->where('email', 'giulia@example.com')->sole();
    $password = session()->get(SessionKey::FLASH_DATA)['credentials']['password'] ?? null;

    expect($operator->tenant_id)->toBe($this->tenant->id)
        ->and($operator->role)->toBe(UserRole::Default)
        ->and($operator->is_bookable)->toBeTrue()
        ->and($operator->services->pluck('id')->all())->toBe([$service->id]);

    expect($password)->toBeString()->not->toBeEmpty();
    $this->post(route('logout'));
    $this->post(route('login.store'), ['email' => 'giulia@example.com', 'password' => $password]);
    $this->assertAuthenticatedAs($operator);
});

test('the staff page lists the operators of the portal only', function () {
    User::factory()->operator($this->tenant)->create(['name' => 'Giulia Bianchi']);
    User::factory()->operator()->create(['name' => 'Altro portale']);

    $this->actingAs($this->manager)->get(route('app.staff'))->assertInertia(fn (Assert $page) => $page
        ->component('app/staff')
        ->has('operators', 1)
        ->where('operators.0.name', 'Giulia Bianchi')
        ->where('managerBookable', false));
});

test('services from another portal are never attached to an operator', function () {
    $foreignService = Service::factory()->create();

    $this->actingAs($this->manager)
        ->post(route('app.staff.store'), staffForm(['email' => 'marco@example.com', 'service_ids' => [$foreignService->id]]))
        ->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'marco@example.com')->sole()->services)->toBeEmpty();
});

test('an operator email must be unique', function () {
    User::factory()->operator($this->tenant)->create(['email' => 'taken@example.com']);

    $this->actingAs($this->manager)
        ->post(route('app.staff.store'), staffForm(['email' => 'taken@example.com']))
        ->assertSessionHasErrors(['email']);
});

test('a manager updates an operator of their portal', function () {
    $operator = User::factory()->operator($this->tenant)->create();
    $service = Service::factory()->for($this->tenant)->create();

    $this->actingAs($this->manager)
        ->put(route('app.staff.update', $operator), staffForm(['email' => $operator->email, 'name' => 'Nome nuovo', 'is_bookable' => false, 'service_ids' => [$service->id]]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Operator updated.');

    $operator->refresh();

    expect($operator->name)->toBe('Nome nuovo')
        ->and($operator->is_bookable)->toBeFalse()
        ->and($operator->services->pluck('id')->all())->toBe([$service->id]);
});

test('a manager cannot edit an operator of another portal', function () {
    $foreignOperator = User::factory()->operator()->create(['name' => 'Estraneo']);

    $this->actingAs($this->manager)
        ->put(route('app.staff.update', $foreignOperator), staffForm(['email' => $foreignOperator->email, 'name' => 'Cambiato']))
        ->assertForbidden();

    expect($foreignOperator->fresh()->name)->toBe('Estraneo');
});

test('a manager can make themselves bookable', function () {
    expect($this->manager->is_bookable)->toBeFalse();

    $this->actingAs($this->manager)
        ->patch(route('app.staff.bookable'))
        ->assertInertiaFlash('toast.message', 'You are now bookable.');

    expect($this->manager->fresh()->is_bookable)->toBeTrue();
});

test('a manager deletes an operator of their portal', function () {
    $operator = User::factory()->operator($this->tenant)->create();

    $this->actingAs($this->manager)
        ->delete(route('app.staff.destroy', $operator))
        ->assertInertiaFlash('toast.message', 'Operator deleted.');

    $this->assertDatabaseMissing('users', ['id' => $operator->id]);
});

test('a manager cannot delete themselves from the staff screen', function () {
    $this->actingAs($this->manager)->delete(route('app.staff.destroy', $this->manager))->assertForbidden();

    $this->assertModelExists($this->manager);
});
