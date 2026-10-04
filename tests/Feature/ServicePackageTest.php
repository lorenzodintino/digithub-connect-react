<?php

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\RescheduleBooking;
use App\Enums\BookingStatus;
use App\Enums\QuestionType;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BookingPhase;
use App\Models\Resource;
use App\Models\ResourceBlock;
use App\Models\Service;
use App\Models\ServiceResourceRequirement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 00:00:00', 'UTC'));
    Mail::fake();
});

/** @return array{Service, Service, Service, User, CarbonImmutable} */
function packageFixture(int $pause = 15): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $attributes = ['duration_minutes' => 30, 'slot_interval_minutes' => 15, 'min_notice_minutes' => 0,
        'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'requires_confirmation' => false,
        'cancellation_notice_hours' => 0, 'max_advance_days' => 60];
    $first = Service::factory()->for($tenant)->create([...$attributes, 'name' => 'Massage', 'price_cents' => 4000]);
    $second = Service::factory()->for($tenant)->create([...$attributes, 'name' => 'Facial', 'price_cents' => 3000]);
    $package = Service::factory()->for($tenant)->create([...$attributes, 'name' => 'Wellness', 'is_package' => true]);
    $operator = User::factory()->operator($tenant)->create();
    foreach ([$first, $second, $package] as $service) {
        $service->operators()->attach($operator);
    }
    foreach (range(0, 6) as $weekday) {
        $operator->availabilityRules()->create(['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '18:00']);
    }
    foreach ([$first, $second] as $position => $service) {
        $package->packageItems()->create(['tenant_id' => $tenant->id, 'service_id' => $service->id,
            'position' => $position, 'pause_after_minutes' => $position === 0 ? $pause : 0]);
    }

    return [$package, $first, $second, $operator, CarbonImmutable::parse('2026-10-02 07:00:00', 'UTC')];
}

/** @param array<int, resource> $resources */
function packageRequirement(Service $service, array $resources): void
{
    $requirement = ServiceResourceRequirement::factory()->for($service)->create(['name' => 'Cabin', 'position' => 0]);
    $requirement->resources()->attach(array_map(fn (Resource $resource) => $resource->id, $resources));
}

function bookPackage(Service $service, User $operator, CarbonImmutable $slot, array $answers = []): Booking
{
    return app(CreateBooking::class)->handle($service, $operator, $slot,
        ['name' => 'Customer', 'email' => 'customer@example.test'], $answers);
}

test('a package books one appointment with ordered phases pauses and summed prices', function () {
    [$package, $first, $second, $operator, $slot] = packageFixture();
    $booking = bookPackage($package, $operator, $slot);

    expect(Booking::count())->toBe(1)
        ->and($booking->ends_at->toDateTimeString())->toBe('2026-10-02 08:15:00')
        ->and($booking->price_cents)->toBe(7000)
        ->and($booking->phases->pluck('service_name')->all())->toBe(['Massage', 'Facial'])
        ->and($booking->phases[0]->pause_after_minutes)->toBe(15)
        ->and($booking->phases[1]->starts_at->toDateTimeString())->toBe('2026-10-02 07:45:00')
        ->and($booking->phases[1]->pause_after_minutes)->toBe(0);
    expect((new SlotFinder($first, $operator))->canBookAt($slot->addMinutes(30)))->toBeFalse();
});

test('a package can override the total price', function () {
    [$package, , , $operator, $slot] = packageFixture();
    $package->update(['use_component_prices' => false, 'price_cents' => 5500]);
    expect(bookPackage($package, $operator, $slot)->price_cents)->toBe(5500);
});

test('the administrator sees the current package duration after a component changes', function () {
    [$package, $first] = packageFixture();
    $first->update(['duration_minutes' => 45]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.portals.show', $package->tenant))->assertOk()
        ->assertSee('Wellness')->assertSee('1 h 30 min');
});

test('the package combines confirmation notice booking window and operator buffers', function () {
    [$package, $first, $second, $operator, $slot] = packageFixture(0);
    $first->update(['min_notice_minutes' => 100, 'buffer_before_minutes' => 20, 'max_advance_days' => 10]);
    $second->update(['requires_confirmation' => true, 'min_notice_minutes' => 200,
        'buffer_after_minutes' => 25, 'cancellation_notice_hours' => 12]);
    $effective = $package->bookingConfiguration();
    expect($effective->min_notice_minutes)->toBe(170)->and($effective->max_advance_days)->toBe(10);
    $booking = bookPackage($package, $operator, $slot);
    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->blocked_starts_at->toDateTimeString())->toBe('2026-10-02 06:40:00')
        ->and($booking->blocked_ends_at->toDateTimeString())->toBe('2026-10-02 08:25:00')
        ->and($booking->customerChangeDeadline()->equalTo($slot->subHours(12)))->toBeTrue();
});

test('a package cannot cross the operator working interval or an internal busy period', function () {
    [$package, $first, , $operator, $slot] = packageFixture();
    expect((new SlotFinder($package, $operator))->canBookAt($slot->setTime(15, 0)))->toBeFalse();
    Booking::factory()->forSlot($first, $operator, $slot->addMinutes(30))->create();
    expect((new SlotFinder($package, $operator))->canBookAt($slot))->toBeFalse();
});

test('an operator must be enabled for every component and inactive components hide packages', function () {
    [$package, , $second, $operator, $slot] = packageFixture();
    $second->operators()->detach($operator);
    expect((new SlotFinder($package, $operator))->canBookAt($slot))->toBeFalse();
    $this->get(route('portal.show', $package->tenant))->assertDontSee('Wellness');
    $second->operators()->attach($operator);
    $second->update(['is_active' => false]);
    $this->get(route('portal.service', [$package->tenant, $package]))->assertNotFound();
});

test('phases may use different cabins and only occupy their own intervals', function () {
    [$package, $first, $second, $operator, $slot] = packageFixture();
    $a = Resource::factory()->for($package->tenant)->create(['name' => 'Cabin A']);
    $b = Resource::factory()->for($package->tenant)->create(['name' => 'Cabin B']);
    packageRequirement($first, [$a]);
    packageRequirement($second, [$b]);
    $booking = bookPackage($package, $operator, $slot);
    expect($booking->resourceAllocations->pluck('resource_id')->all())->toBe([$a->id, $b->id])
        ->and($booking->resourceAllocations[0]->ends_at->equalTo($slot->addMinutes(30)))->toBeTrue()
        ->and($booking->resourceAllocations[1]->starts_at->equalTo($slot->addMinutes(45)))->toBeTrue()
        ->and($booking->phases[0]->resourceAllocations->count())->toBe(1)
        ->and($booking->phases[1]->resourceAllocations->count())->toBe(1);
});

test('the same cabin can be reused when phase preparation and cleanup fit the pause', function () {
    [$package, $first, $second, $operator, $slot] = packageFixture(20);
    $a = Resource::factory()->for($package->tenant)->create(['preparation_minutes' => 10, 'cleanup_minutes' => 10]);
    packageRequirement($first, [$a]);
    packageRequirement($second, [$a]);
    expect((new SlotFinder($package, $operator))->canBookAt($slot))->toBeTrue();
    expect(bookPackage($package, $operator, $slot)->resourceAllocations()->count())->toBe(2);
});

test('overlapping preparation and cleanup require another cabin and backtracking finds it', function () {
    [$package, $first, $second, $operator, $slot] = packageFixture(0);
    $a = Resource::factory()->for($package->tenant)->create(['cleanup_minutes' => 15]);
    $b = Resource::factory()->for($package->tenant)->create();
    packageRequirement($first, [$a, $b]);
    packageRequirement($second, [$a]);
    expect(bookPackage($package, $operator, $slot)->resourceAllocations->pluck('resource_id')->all())->toBe([$b->id, $a->id]);
});

test('an impossible resource sequence leaves no partial appointment', function () {
    [$package, $first, $second, $operator, $slot] = packageFixture(0);
    $a = Resource::factory()->for($package->tenant)->create(['cleanup_minutes' => 15]);
    packageRequirement($first, [$a]);
    packageRequirement($second, [$a]);
    expect(fn () => bookPackage($package, $operator, $slot))->toThrow(SlotUnavailableException::class);
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('booking_phases', 0);
    $this->assertDatabaseCount('resource_allocations', 0);
    Mail::assertNothingQueued();
});

test('maintenance in a later phase rejects the entire package', function () {
    [$package, , $second, $operator, $slot] = packageFixture();
    $a = Resource::factory()->for($package->tenant)->create();
    packageRequirement($second, [$a]);
    ResourceBlock::factory()->for($a, 'resource')->create(['tenant_id' => $package->tenant_id,
        'starts_at' => $slot->addMinutes(50), 'ends_at' => $slot->addMinutes(60)]);
    expect((new SlotFinder($package, $operator))->canBookAt($slot))->toBeFalse();
});

test('component and package questions are validated and saved with contextual labels', function () {
    [$package, $first, , $operator, $slot] = packageFixture();
    $question = $first->questions()->create(['label' => 'Allergies?', 'type' => QuestionType::ShortText, 'is_required' => true, 'position' => 0]);
    $own = $package->questions()->create(['label' => 'Gift?', 'type' => QuestionType::ShortText, 'is_required' => false, 'position' => 0]);
    $page = Livewire::test('pages::portal.service', ['tenant' => $package->tenant, 'service' => $package])
        ->set('selectedSlot', $slot->toIso8601String())->set('customerName', 'Customer')
        ->set('customerEmail', 'customer@example.test')->set('privacyAccepted', true)
        ->call('book')->assertHasErrors(['answers.'.$question->id]);
    $page->set('answers.'.$question->id, 'None')->set('answers.'.$own->id, 'Yes')
        ->call('book')->assertHasNoErrors();
    expect(Booking::sole()->answers->pluck('label')->all())->toBe(['Gift?', 'Massage · Allergies?']);
});

test('moving a package preserves its booked programme and price after catalog changes', function () {
    [$package, $first, $second, $operator, $slot] = packageFixture();
    $booking = bookPackage($package, $operator, $slot);
    $first->update(['name' => 'New massage', 'duration_minutes' => 60, 'price_cents' => 9000]);
    $package->packageItems()->delete();
    foreach ([$second, $first] as $position => $service) {
        $package->packageItems()->create(['tenant_id' => $package->tenant_id, 'service_id' => $service->id, 'position' => $position, 'pause_after_minutes' => 0]);
    }
    $moved = app(RescheduleBooking::class)->handle($booking, $slot->addDays(1));
    expect($moved->price_cents)->toBe(7000)->and($moved->phases->pluck('service_name')->all())->toBe(['Massage', 'Facial'])
        ->and($moved->phases[0]->pause_after_minutes)->toBe(15)
        ->and($moved->ends_at->equalTo($slot->addDays(1)->addMinutes(75)))->toBeTrue();
    $this->get(route('portal.booking', [$package->tenant, $moved]))->assertOk()->assertSee('Massage')->assertDontSee('New massage');
});

test('cancellation releases all package resources together and keeps its programme', function () {
    [$package, $first, $second, $operator, $slot] = packageFixture();
    $a = Resource::factory()->for($package->tenant)->create();
    packageRequirement($first, [$a]);
    packageRequirement($second, [$a]);
    $booking = bookPackage($package, $operator, $slot);
    app(CancelBooking::class)->handle($booking);
    expect($booking->fresh()->phases()->count())->toBe(2)
        ->and($booking->resourceAllocations()->whereNull('released_at')->count())->toBe(0)
        ->and((new SlotFinder($package->fresh(), $operator))->canBookAt($slot))->toBeTrue();
    $manager = User::factory()->manager($package->tenant)->create();
    Livewire::actingAs($manager)->test('pages::app.bookings')->call('showBooking', $booking->id)
        ->assertSee('Resource history')->assertSee($a->name)->assertSee('Package programme');
});

test('a failed phase write rolls back the whole package', function () {
    [$package, , , $operator, $slot] = packageFixture();
    $event = 'eloquent.creating: '.BookingPhase::class;
    Event::listen($event, fn () => throw new RuntimeException('phase failed'));
    try {
        expect(fn () => bookPackage($package, $operator, $slot))->toThrow(RuntimeException::class, 'phase failed');
    } finally {
        Event::forget($event);
    }
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('booking_phases', 0);
    Mail::assertNothingQueued();
});

test('the manager configures component order pauses and calculated duration', function () {
    [$package, $first, $second, $operator] = packageFixture();
    $manager = User::factory()->manager($package->tenant)->create();
    Livewire::actingAs($manager)->test('pages::app.services')->call('createService')
        ->set('form.name', 'New package')->set('form.is_package', true)
        ->set('form.package_items', [['service_id' => $first->id, 'pause_after_minutes' => 20], ['service_id' => $second->id, 'pause_after_minutes' => 0]])
        ->set('form.operator_ids', [$operator->id])->call('movePackageItem', 0, 1)
        ->set('form.package_items.0.pause_after_minutes', 10)
        ->call('saveService')->assertHasNoErrors();
    $created = Service::where('name', 'New package')->sole();
    expect($created->is_package)->toBeTrue()->and($created->duration_minutes)->toBe(70)
        ->and($created->price_cents)->toBe(7000)
        ->and($created->packageItems->pluck('service_id')->all())->toBe([$second->id, $first->id])
        ->and($created->packageItems[1]->pause_after_minutes)->toBe(0);
});

test('invalid package compositions are rejected without writing', function (string $invalid) {
    [$package, $first, $second, $operator] = packageFixture();
    $manager = User::factory()->manager($package->tenant)->create();
    $rows = [['service_id' => $first->id, 'pause_after_minutes' => 15], ['service_id' => $second->id, 'pause_after_minutes' => 0]];
    $error = 'form.package_items.1.service_id';
    if ($invalid === 'duplicate') {
        $rows[1]['service_id'] = $first->id;
    } elseif ($invalid === 'foreign') {
        $rows[1]['service_id'] = Service::factory()->create()->id;
    } elseif ($invalid === 'nested') {
        $rows[1]['service_id'] = $package->id;
    } elseif ($invalid === 'group') {
        $second->update(['capacity' => 2]);
    } elseif ($invalid === 'currency') {
        $second->update(['currency' => 'USD']);
    } elseif ($invalid === 'operator') {
        $second->operators()->detach($operator);
        $error = 'form.operator_ids';
    } elseif ($invalid === 'pause') {
        $rows[0]['pause_after_minutes'] = -1;
        $error = 'form.package_items.0.pause_after_minutes';
    }
    Livewire::actingAs($manager)->test('pages::app.services')->call('createService')
        ->set('form.name', 'Invalid package')->set('form.is_package', true)
        ->set('form.package_items', $rows)->set('form.operator_ids', [$operator->id])
        ->call('saveService')->assertHasErrors([$error]);
    $this->assertDatabaseMissing('services', ['name' => 'Invalid package']);
})->with(['duplicate', 'foreign', 'nested', 'group', 'currency', 'operator', 'pause']);

test('a component cannot be deleted or converted to group while used by packages', function () {
    [$package, $first] = packageFixture();
    $manager = User::factory()->manager($package->tenant)->create();
    Livewire::actingAs($manager)->test('pages::app.services')->call('editService', $first->slug)
        ->set('form.capacity', 2)->call('saveService')->assertHasErrors(['form.capacity']);
    Livewire::actingAs($manager)->test('pages::app.services')->call('confirmDelete', $first->id)->call('deleteService');
    $this->assertDatabaseHas('services', ['id' => $first->id]);
});

test('duplicate package phases have readable Italian errors once per field', function () {
    [$package, $first, , $operator] = packageFixture();
    $manager = User::factory()->manager($package->tenant)->create(['locale' => 'it']);
    app()->setLocale('it');

    $page = Livewire::actingAs($manager)->test('pages::app.services')->call('createService')
        ->set('form.name', 'Duplicato')->set('form.is_package', true)
        ->set('form.package_items', [
            ['service_id' => $first->id, 'pause_after_minutes' => 0],
            ['service_id' => $first->id, 'pause_after_minutes' => 0],
        ])->set('form.operator_ids', [$operator->id])->call('saveService')
        ->assertHasErrors(['form.package_items.0.service_id' => 'distinct'])
        ->assertSee('Il campo Servizio contiene un valore duplicato.');

    expect(substr_count($page->html(), 'Il campo Servizio contiene un valore duplicato.'))->toBe(2);
    $this->assertDatabaseMissing('services', ['name' => 'Duplicato']);
});

test('the public summary recalculates the package after hydration and price changes', function () {
    [$package, $first] = packageFixture();
    $page = Livewire::test('pages::portal.service', ['tenant' => $package->tenant, 'service' => $package])->assertSee('70,00');
    $first->update(['price_cents' => 5000]);
    $page->call('nextMonth')->assertSee('80,00')->assertSee('Package programme');
});

test('moving into a resource conflict preserves all original phases and allocations', function () {
    [$package, $first, $second, $operator, $slot] = packageFixture();
    $a = Resource::factory()->for($package->tenant)->create();
    packageRequirement($first, [$a]);
    packageRequirement($second, [$a]);
    $booking = bookPackage($package, $operator, $slot);
    $phaseIds = $booking->phases->modelKeys();
    $allocationIds = $booking->resourceAllocations->modelKeys();
    ResourceBlock::factory()->for($a, 'resource')->create(['tenant_id' => $package->tenant_id,
        'starts_at' => $slot->addDay()->addMinutes(50), 'ends_at' => $slot->addDay()->addMinutes(60)]);
    expect(fn () => app(RescheduleBooking::class)->handle($booking, $slot->addDay()))->toThrow(SlotUnavailableException::class);
    expect($booking->fresh()->starts_at->equalTo($slot))->toBeTrue()
        ->and($booking->fresh()->phases->modelKeys())->toBe($phaseIds)
        ->and($booking->fresh()->resourceAllocations->modelKeys())->toBe($allocationIds)
        ->and($booking->resourceAllocations()->whereNull('released_at')->count())->toBe(2);
});

test('a package phase stays within its own final bookable day', function () {
    [$package, , $second, $operator] = packageFixture();
    $second->update(['max_advance_days' => 1]);
    expect((new SlotFinder($package, $operator))->latestBookableAt()->toDateTimeString())->toBe('2026-10-02 21:14:59');
});

test('a package needs at least two phases and rejects direct resource requirements', function () {
    [$package, $first] = packageFixture();
    $manager = User::factory()->manager($package->tenant)->create();
    $page = Livewire::actingAs($manager)->test('pages::app.services')->call('createService')
        ->set('form.name', 'Too short')->set('form.is_package', true)
        ->set('form.package_items', [['service_id' => $first->id, 'pause_after_minutes' => 0]])
        ->call('saveService')->assertHasErrors(['form.package_items']);
    $page->set('form.resource_requirements', [['name' => 'Extra cabin', 'resource_ids' => []]])
        ->call('saveService')->assertHasErrors(['form.resource_requirements']);
});

test('the package programme and agreed price are included in mail and agenda', function () {
    [$package, $first, , $operator, $slot] = packageFixture();
    $booking = bookPackage($package, $operator, $slot);
    $first->update(['name' => 'New massage', 'price_cents' => 9900]);
    $html = view('mail.partials.booking-summary', ['booking' => $booking->fresh(), 'timezone' => 'Europe/Rome'])->render();
    expect($html)->toContain('Massage', 'Facial', '09:45', '70,00')->not->toContain('New massage');
    $manager = User::factory()->manager($package->tenant)->create();
    Livewire::actingAs($manager)->test('pages::app.bookings')->call('showBooking', $booking->id)
        ->assertSee('Package programme')->assertSee('Massage')->assertSee('09:45');
});

test('a package does not prevent deleting its whole portal', function () {
    [$package, , , $operator, $slot] = packageFixture();
    bookPackage($package, $operator, $slot);
    $tenant = $package->tenant;
    $tenant->delete();
    $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    $this->assertDatabaseCount('service_package_items', 0);
    $this->assertDatabaseCount('booking_phases', 0);
});

test('a booked component stays individual even after being removed from the catalog package', function () {
    [$package, $first, , $operator, $slot] = packageFixture();
    bookPackage($package, $operator, $slot);
    $package->packageItems()->delete();
    $manager = User::factory()->manager($package->tenant)->create();
    Livewire::actingAs($manager)->test('pages::app.services')->call('editService', $first->slug)
        ->set('form.capacity', 2)->call('saveService')->assertHasErrors(['form.capacity']);
});

test('a manager can turn a service with no package commitments into a group service', function () {
    $tenant = Tenant::factory()->create();
    $service = Service::factory()->for($tenant)->create();
    $manager = User::factory()->manager($tenant)->create();
    Livewire::actingAs($manager)->test('pages::app.services')->call('editService', $service->slug)
        ->set('form.capacity', 2)->call('saveService')->assertHasNoErrors();
    expect($service->fresh()->capacity)->toBe(2);
});

test('ten package phases alternate cabins when cleanup overlaps the next phase', function () {
    [$package, , , $operator, $slot] = packageFixture();
    $cabins = Resource::factory()->count(2)->for($package->tenant)->create(['cleanup_minutes' => 20]);
    $components = Service::factory()->count(10)->for($package->tenant)->create([
        'duration_minutes' => 10, 'price_cents' => 1000, 'min_notice_minutes' => 0,
        'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
    ]);
    $package->packageItems()->delete();
    foreach ($components as $position => $component) {
        $component->operators()->attach($operator);
        packageRequirement($component, $cabins->all());
        $package->packageItems()->create([
            'tenant_id' => $package->tenant_id, 'service_id' => $component->id,
            'position' => $position, 'pause_after_minutes' => $position === 9 ? 0 : 10,
        ]);
    }

    $booking = bookPackage($package, $operator, $slot);

    expect($booking->phases)->toHaveCount(10);
    expect($booking->resourceAllocations)->toHaveCount(10);
    expect($booking->ends_at->toDateTimeString())->toBe('2026-10-02 10:10:00');
    expect($booking->price_cents)->toBe(10000);
    $allocations = $booking->resourceAllocations->sortBy('starts_at')->values();
    foreach ($allocations as $position => $allocation) {
        expect($allocation->resource_id)->toBe($cabins[$position % 2]->id);
    }
});

test('a manager cannot save a package with eleven distinct phases', function () {
    [$package, , , $operator] = packageFixture();
    $components = Service::factory()->count(11)->for($package->tenant)->create();
    foreach ($components as $component) {
        $component->operators()->attach($operator);
    }

    $manager = User::factory()->manager($package->tenant)->create();
    $rows = $components->map(fn (Service $component) => ['service_id' => $component->id, 'pause_after_minutes' => 10])->all();

    Livewire::actingAs($manager)->test('pages::app.services')->call('createService')
        ->set('form.name', 'Eleven phases')->set('form.is_package', true)
        ->set('form.operator_ids', [$operator->id])->set('form.package_items', $rows)
        ->call('saveService')->assertHasErrors(['form.package_items' => 'max']);
    $this->assertDatabaseMissing('services', ['name' => 'Eleven phases']);
});
