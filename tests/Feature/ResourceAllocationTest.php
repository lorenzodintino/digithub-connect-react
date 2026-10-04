<?php

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\ConfirmBooking;
use App\Actions\Bookings\CreateBooking;
use App\Actions\Bookings\RescheduleBooking;
use App\Enums\BookingStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use App\Models\ResourceBlock;
use App\Models\Service;
use App\Models\ServiceResourceRequirement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GroupSlotFinder;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 00:00:00', 'UTC'));
});

/**
 * @param  array<string, mixed>  $attributes
 * @return array{Service, User, User, App\Models\Resource, CarbonImmutable}
 */
function portalWithResources(array $attributes = []): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $service = Service::factory()->for($tenant)->create([
        'duration_minutes' => 30, 'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0, 'requires_confirmation' => false,
        ...$attributes,
    ]);
    $first = resourceOperator($service);
    $second = resourceOperator($service);
    $resource = Resource::factory()->for($tenant)->create(['name' => 'Cabin 1']);
    resourceRequirement($service, [$resource]);

    return [$service, $first, $second, $resource, CarbonImmutable::parse('2026-10-02 07:00:00', 'UTC')];
}

function resourceOperator(Service $service): User
{
    $operator = User::factory()->operator($service->tenant)->create();
    $service->operators()->attach($operator);
    foreach (range(0, 6) as $weekday) {
        $operator->availabilityRules()->create(['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '13:00']);
    }

    return $operator;
}

/** @param array<int, App\Models\Resource> $resources */
function resourceRequirement(Service $service, array $resources, string $name = 'Cabin'): ServiceResourceRequirement
{
    $requirement = ServiceResourceRequirement::factory()->for($service)->create([
        'name' => $name, 'position' => $service->resourceRequirements()->count(),
    ]);
    $requirement->resources()->attach(array_map(fn ($resource) => $resource->id, $resources));

    return $requirement;
}

function resourceBooking(Service $service, ?User $operator, CarbonImmutable $slot, string $email = 'customer@example.test'): Booking
{
    return app(CreateBooking::class)->handle($service, $operator, $slot, ['name' => 'Customer', 'email' => $email]);
}

test('pending bookings snapshot every required resource and its own preparation and cleanup', function () {
    [$service, $operator, , $resource, $slot] = portalWithResources(['requires_confirmation' => true, 'buffer_before_minutes' => 30]);
    $resource->update(['preparation_minutes' => 10, 'cleanup_minutes' => 15]);
    $laser = Resource::factory()->for($service->tenant)->create(['name' => 'Laser']);
    resourceRequirement($service, [$laser], 'Machine');

    $booking = resourceBooking($service, $operator, $slot);

    expect($booking->status)->toBe(BookingStatus::Pending);
    $allocation = $booking->resourceAllocations()->where('resource_id', $resource->id)->sole();
    expect($allocation->resource_name)->toBe('Cabin 1')
        ->and($allocation->requirement_name)->toBe('Cabin')
        ->and($allocation->starts_at->toDateTimeString())->toBe('2026-10-02 06:50:00')
        ->and($allocation->ends_at->toDateTimeString())->toBe('2026-10-02 07:45:00')
        ->and($allocation->preparation_minutes)->toBe(10)
        ->and($allocation->cleanup_minutes)->toBe(15);
    $this->assertDatabaseCount('resource_allocations', 2);
});

test('different operators cannot book the same resource and a rejected booking creates no partial records', function (bool $requiresConfirmation) {
    Mail::fake();
    [$service, $first, $second, , $slot] = portalWithResources(['requires_confirmation' => $requiresConfirmation]);
    resourceBooking($service, $first, $slot);

    expect(fn () => resourceBooking($service, $second, $slot, 'rejected@example.test'))->toThrow(SlotUnavailableException::class);

    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('resource_allocations', 1);
    $this->assertDatabaseMissing('customers', ['email' => 'rejected@example.test']);
    Mail::assertQueuedCount(2);
})->with(['confirmed' => false, 'pending' => true]);

test('two interchangeable cabins allow two concurrent appointments but not a third', function () {
    [$service, $first, $second, $resource, $slot] = portalWithResources();
    $alternative = Resource::factory()->for($service->tenant)->create(['name' => 'Cabin 2']);
    $service->resourceRequirements()->sole()->resources()->attach($alternative);
    $third = resourceOperator($service);

    $one = resourceBooking($service, $first, $slot);
    $two = resourceBooking($service, $second, $slot, 'second@example.test');

    expect($one->resourceAllocations()->sole()->resource_id)->toBe($resource->id);
    expect($two->resourceAllocations()->sole()->resource_id)->toBe($alternative->id);
    expect(fn () => resourceBooking($service, $third, $slot))->toThrow(SlotUnavailableException::class);
    $this->assertDatabaseCount('bookings', 2);
});

test('every requirement must have an active available resource in the same portal', function (string $failure) {
    [$service, $operator, , $resource, $slot] = portalWithResources();
    $requirement = $service->resourceRequirements()->sole();
    match ($failure) {
        'inactive' => $resource->update(['is_active' => false]),
        'empty' => $requirement->resources()->detach(),
        'foreign' => $requirement->resources()->sync([Resource::factory()->create()->id]),
        'missing second' => resourceRequirement($service, [], 'Machine'),
        'repeated' => resourceRequirement($service, [$resource], 'Machine'),
    };

    expect((new SlotFinder($service, $operator))->canBookAt($slot))->toBeFalse();
    expect(fn () => resourceBooking($service, $operator, $slot))->toThrow(SlotUnavailableException::class);
    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('resource_allocations', 0);
})->with(['inactive', 'empty', 'foreign', 'missing second', 'repeated']);

test('resource conflicts use open interval boundaries including preparation and cleanup', function (string $from, string $to, bool $available) {
    [$service, $operator, , $resource, $slot] = portalWithResources();
    $resource->update(['preparation_minutes' => 10, 'cleanup_minutes' => 15]);
    ResourceBlock::factory()->for($resource)->create([
        'starts_at' => CarbonImmutable::parse('2026-10-02 '.$from, 'UTC'),
        'ends_at' => CarbonImmutable::parse('2026-10-02 '.$to, 'UTC'),
    ]);

    expect((new SlotFinder($service, $operator))->canBookAt($slot))->toBe($available);
})->with([
    'touches preparation' => ['06:30', '06:50', true],
    'overlaps preparation' => ['06:49', '06:51', false],
    'overlaps appointment' => ['07:00', '07:10', false],
    'overlaps cleanup' => ['07:44', '07:46', false],
    'touches cleanup' => ['07:45', '08:00', true],
]);

test('the allocator chooses a free active alternative when another is under maintenance', function () {
    [$service, $operator, , $resource, $slot] = portalWithResources();
    $alternative = Resource::factory()->for($service->tenant)->create();
    $service->resourceRequirements()->sole()->resources()->attach($alternative);
    ResourceBlock::factory()->for($resource)->create(['starts_at' => $slot, 'ends_at' => $slot->addHour()]);

    $booking = resourceBooking($service, $operator, $slot);

    expect($booking->resourceAllocations()->sole()->resource_id)->toBe($alternative->id);
});

test('catalog changes preserve stored resource names and occupation windows', function () {
    [$service, $operator, $other, $resource, $slot] = portalWithResources();
    $resource->update(['cleanup_minutes' => 30]);
    $booking = resourceBooking($service, $operator, $slot);
    $resource->update(['name' => 'Renamed cabin', 'cleanup_minutes' => 0]);

    expect($booking->resourceAllocations()->sole()->resource_name)->toBe('Cabin 1')
        ->and($booking->resourceAllocations()->sole()->ends_at->toDateTimeString())->toBe('2026-10-02 08:00:00');
    expect((new SlotFinder($service, $other))->canBookAt($slot->addMinutes(30)))->toBeFalse();
    expect((new SlotFinder($service, $other))->canBookAt($slot->addHour()))->toBeTrue();
});

test('cancellation frees individual resources and keeps their snapshots', function () {
    [$service, $first, $second, , $slot] = portalWithResources();
    $booking = resourceBooking($service, $first, $slot);

    app(CancelBooking::class)->handle($booking);
    resourceBooking($service, $second, $slot);

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
    expect($booking->resourceAllocations()->count())->toBe(1);
    expect(ResourceAllocation::query()->blocking()->count())->toBe(1);
});

test('rescheduling can reuse its own resource while preserving the public token', function () {
    [$service, $operator, , $resource, $slot] = portalWithResources();
    $resource->update(['cleanup_minutes' => 30]);
    $booking = resourceBooking($service, $operator, $slot);
    $uid = $booking->uid;

    app(RescheduleBooking::class)->handle($booking, $slot->addMinutes(30));

    expect($booking->fresh()->uid)->toBe($uid);
    expect($booking->resourceAllocations()->whereNull('released_at')->sole()->starts_at->toDateTimeString())->toBe('2026-10-02 07:30:00');
    expect($booking->resourceAllocations()->whereNotNull('released_at')->count())->toBe(1);
});

test('rescheduling reassigns an alternative and a failed move leaves the previous booking intact', function () {
    [$service, $operator, , $resource, $slot] = portalWithResources();
    $alternative = Resource::factory()->for($service->tenant)->create();
    $service->resourceRequirements()->sole()->resources()->attach($alternative);
    $booking = resourceBooking($service, $operator, $slot);
    ResourceBlock::factory()->for($resource)->create(['starts_at' => $slot->addHour(), 'ends_at' => $slot->addHours(2)]);

    app(RescheduleBooking::class)->handle($booking, $slot->addHour());

    expect($booking->resourceAllocations()->whereNull('released_at')->sole()->resource_id)->toBe($alternative->id);
    ResourceBlock::factory()->for($resource)->create(['starts_at' => $slot->addHours(2), 'ends_at' => $slot->addHours(3)]);
    ResourceBlock::factory()->for($alternative)->create(['starts_at' => $slot->addHours(2), 'ends_at' => $slot->addHours(3)]);
    expect(fn () => app(RescheduleBooking::class)->handle($booking, $slot->addHours(2)))->toThrow(SlotUnavailableException::class);
    expect($booking->fresh()->starts_at->toDateTimeString())->toBe('2026-10-02 08:00:00');
    expect($booking->resourceAllocations()->whereNull('released_at')->sole()->resource_id)->toBe($alternative->id);
    $this->assertDatabaseCount('resource_allocations', 2);
});

test('adding requirements never allocates resources retroactively to existing bookings', function () {
    [$service, $operator, , $resource, $slot] = portalWithResources();
    $service->resourceRequirements()->delete();
    $booking = resourceBooking($service, $operator, $slot);

    resourceRequirement($service, [$resource]);

    expect($booking->resourceAllocations()->count())->toBe(0);
});

test('a group session occupies resources once and releases them only when its last booking is cancelled', function () {
    [$service, , , $resource, $slot] = portalWithResources(['capacity' => 3]);
    $first = resourceBooking($service, null, $slot);
    $second = resourceBooking($service, null, $slot, 'second@example.test');
    expect(ResourceAllocation::query()->count())->toBe(1);
    expect($first->sharedResourceAllocations()->sole()->id)->toBe($second->sharedResourceAllocations()->sole()->id);

    app(CancelBooking::class)->handle($first);
    expect($resource->hasUpcomingAllocations())->toBeTrue();
    app(CancelBooking::class)->handle($second);
    expect($resource->hasUpcomingAllocations())->toBeFalse();
    expect(ResourceAllocation::query()->sole()->released_at)->not->toBeNull();

    $resource->update(['name' => 'New cabin name']);
    $third = resourceBooking($service, null, $slot, 'third@example.test');
    expect($third->sharedResourceAllocations()->sole()->resource_name)->toBe('New cabin name');
    expect($first->sharedResourceAllocations()->sole()->resource_name)->toBe('Cabin 1');
    expect(ResourceAllocation::query()->blocking()->count())->toBe(1);
});

test('an empty group session rechecks resources before reopening', function () {
    [$service, , , $resource, $slot] = portalWithResources(['capacity' => 3]);
    $booking = resourceBooking($service, null, $slot);
    app(CancelBooking::class)->handle($booking);
    ResourceBlock::factory()->for($resource)->create(['starts_at' => $slot, 'ends_at' => $slot->addHour()]);

    expect((new GroupSlotFinder($service))->canBookAt($slot))->toBeFalse();
    expect(fn () => resourceBooking($service, null, $slot))->toThrow(SlotUnavailableException::class);
    $this->assertDatabaseCount('bookings', 1);
    expect(ResourceAllocation::query()->blocking()->count())->toBe(0);
});

test('group sessions and individual services compete for the same physical resources', function () {
    [$group, , , $resource, $slot] = portalWithResources(['capacity' => 3]);
    $individual = Service::factory()->for($group->tenant)->create(['duration_minutes' => 30, 'slot_interval_minutes' => 30, 'min_notice_minutes' => 0]);
    $operator = resourceOperator($individual);
    resourceRequirement($individual, [$resource]);
    resourceBooking($group, null, $slot);

    expect((new SlotFinder($individual, $operator))->canBookAt($slot))->toBeFalse();
    expect(fn () => resourceBooking($individual, $operator, $slot))->toThrow(SlotUnavailableException::class);
});

test('a new teacher can join a group session whose resources are already allocated', function () {
    [$service, , , , $slot] = portalWithResources(['capacity' => 3]);
    $booking = resourceBooking($service, null, $slot);
    $teacher = resourceOperator($service);
    $manager = User::factory()->manager($service->tenant)->create();

    Livewire::actingAs($manager)->test('pages::app.bookings')
        ->call('showBooking', $booking->id)->call('addSessionOperator', $teacher->id)->assertHasNoErrors();

    expect($booking->session->operators()->whereKey($teacher->id)->exists())->toBeTrue();
    $this->assertDatabaseCount('resource_allocations', 1);
});

test('resource unavailability rejects occupied preparation time and allows touching the occupation end', function () {
    [$service, $operator, , $resource, $slot] = portalWithResources();
    $resource->update(['preparation_minutes' => 10]);
    resourceBooking($service, $operator, $slot);
    $manager = User::factory()->manager($service->tenant)->create();

    $page = Livewire::actingAs($manager)->test('pages::app.resources')
        ->call('blockResource', $resource->id)
        ->set('blockForm.starts_at', '2026-10-02T08:45')->set('blockForm.ends_at', '2026-10-02T08:55')
        ->call('saveBlock')->assertHasErrors(['blockForm.starts_at'])
        ->assertSee('This period overlaps a resource booking. Cancel or move it first.');
    $this->assertDatabaseCount('resource_blocks', 0);
    $page->set('blockForm.starts_at', '2026-10-02T09:30')->set('blockForm.ends_at', '2026-10-02T10:00')
        ->call('saveBlock')->assertHasNoErrors();
    $this->assertDatabaseCount('resource_blocks', 1);
});

test('deactivation and deletion reject future occupation even after removing service requirements', function () {
    [$service, $operator, , $resource, $slot] = portalWithResources();
    resourceBooking($service, $operator, $slot);
    $service->resourceRequirements()->delete();
    $manager = User::factory()->manager($service->tenant)->create();

    $page = Livewire::actingAs($manager)->test('pages::app.resources')
        ->call('editResource', $resource->id)->set('form.is_active', false)->call('saveResource')
        ->assertHasErrors(['form.is_active'])
        ->assertSee('This resource has upcoming bookings. Cancel or move them before deactivating it.');
    expect(substr_count($page->html(), 'This resource has upcoming bookings. Cancel or move them before deactivating it.'))->toBe(1);

    $page->call('confirmDelete', $resource->id)->call('deleteResource')->assertHasErrors(['deletingResourceId']);

    expect($resource->fresh()->is_active)->toBeTrue();
    $this->assertModelExists($resource);
});

test('deleting an unused resource after cancellation keeps its booking history', function () {
    [$service, $operator, , $resource, $slot] = portalWithResources();
    $booking = resourceBooking($service, $operator, $slot);
    app(CancelBooking::class)->handle($booking);
    $service->resourceRequirements()->delete();
    $manager = User::factory()->manager($service->tenant)->create();

    Livewire::actingAs($manager)->test('pages::app.resources')
        ->call('confirmDelete', $resource->id)->call('deleteResource')->assertHasNoErrors();

    $this->assertModelMissing($resource);
    expect($booking->resourceAllocations()->sole()->resource_name)->toBe('Cabin 1')
        ->and($booking->resourceAllocations()->sole()->resource_id)->toBeNull();
});

test('the public form rejects a slot whose resource was taken after selection', function () {
    [$service, $first, $second, , $slot] = portalWithResources();
    $page = Livewire::test('pages::portal.service', ['tenant' => $service->tenant, 'service' => $service])
        ->set('operatorId', $second->id)->call('selectSlot', $slot->toIso8601String())
        ->set('customerName', 'Second customer')->set('customerEmail', 'second@example.test')->set('privacyAccepted', true);
    resourceBooking($service, $first, $slot);

    $page->call('book')->assertHasErrors(['selectedSlot']);

    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseMissing('customers', ['email' => 'second@example.test']);
});

test('agenda details display escaped resource snapshots and occupation times', function () {
    [$service, $operator, , $resource, $slot] = portalWithResources();
    $resource->update(['name' => '<b>Cabin</b>', 'preparation_minutes' => 10]);
    $booking = resourceBooking($service, $operator, $slot);
    $manager = User::factory()->manager($service->tenant)->create(['timezone' => 'Europe/Rome']);

    Livewire::actingAs($manager)->test('pages::app.bookings')->call('showBooking', $booking->id)
        ->assertSee('Assigned resources')->assertSee('&lt;b&gt;Cabin&lt;/b&gt;', false)
        ->assertDontSee('<b>Cabin</b>', false)->assertSee('02/10/2026 08:50');
});

test('resource preparation spilling over midnight sees the previous day maintenance', function () {
    [$service, $operator, , $resource] = portalWithResources();
    $operator->availabilityRules()->delete();
    $operator->availabilityRules()->create(['weekday' => 5, 'starts_at' => '00:00', 'ends_at' => '02:00']);
    $resource->update(['preparation_minutes' => 20]);
    $slot = CarbonImmutable::parse('2026-10-02 00:00', 'Europe/Rome')->utc();
    ResourceBlock::factory()->for($resource)->create(['starts_at' => $slot->subMinutes(15), 'ends_at' => $slot->subMinutes(5)]);

    expect((new SlotFinder($service, $operator))->canBookAt($slot))->toBeFalse();
});

test('winter booking windows and resource occupations are stored in UTC', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-01', 'UTC'));
    [$service, $operator, , $resource] = portalWithResources();
    $resource->update(['preparation_minutes' => 10]);

    $booking = resourceBooking($service, $operator, CarbonImmutable::parse('2026-12-02 09:00', 'Europe/Rome'));

    expect($booking->resourceAllocations()->sole()->starts_at->toDateTimeString())->toBe('2026-12-02 07:50:00');
});

test('a stale confirmation cannot resurrect a cancelled booking and reclaim its resource', function () {
    [$service, $first, $second, , $slot] = portalWithResources(['requires_confirmation' => true]);
    $booking = resourceBooking($service, $first, $slot);
    $stale = $booking->fresh();
    app(CancelBooking::class)->handle($booking);
    resourceBooking($service, $second, $slot, 'second@example.test');

    expect(fn () => app(ConfirmBooking::class)->handle($stale))->toThrow(HttpException::class);

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
    expect(ResourceAllocation::query()->blocking()->count())->toBe(1);
});

test('a storage failure rolls back bookings customers and partially written resource assignments', function (int $capacity) {
    Mail::fake();
    [$service, $operator, , , $slot] = portalWithResources(['capacity' => $capacity]);
    $laser = Resource::factory()->for($service->tenant)->create();
    resourceRequirement($service, [$laser], 'Machine');
    $created = 0;
    $event = 'eloquent.creating: '.ResourceAllocation::class;
    Event::listen($event, function () use (&$created): void {
        if (++$created === 2) {
            throw new RuntimeException('Simulated allocation storage failure');
        }
    });

    try {
        expect(fn () => resourceBooking($service, $operator, $slot))->toThrow(RuntimeException::class, 'Simulated allocation storage failure');
    } finally {
        Event::forget($event);
    }

    $this->assertDatabaseCount('bookings', 0);
    $this->assertDatabaseCount('customers', 0);
    $this->assertDatabaseCount('service_sessions', 0);
    $this->assertDatabaseCount('resource_allocations', 0);
    Mail::assertNothingQueued();
})->with(['individual' => 1, 'group' => 3]);

test('a failed resource write during rescheduling restores the old occupation and booking', function () {
    [$service, $operator, , , $slot] = portalWithResources();
    $booking = resourceBooking($service, $operator, $slot);
    Mail::fake();
    $event = 'eloquent.creating: '.ResourceAllocation::class;
    Event::listen($event, fn () => throw new RuntimeException('Simulated move failure'));

    try {
        expect(fn () => app(RescheduleBooking::class)->handle($booking, $slot->addHour()))->toThrow(RuntimeException::class, 'Simulated move failure');
    } finally {
        Event::forget($event);
    }

    expect($booking->fresh()->starts_at->toDateTimeString())->toBe('2026-10-02 07:00:00');
    expect($booking->resourceAllocations()->sole()->released_at)->toBeNull();
    $this->assertDatabaseCount('resource_allocations', 1);
    Mail::assertNothingQueued();
});

test('simultaneous requests with different operators book a shared resource only once', function (int $capacity) {
    $this->travelBack();
    $database = tempnam(sys_get_temp_dir(), 'resource-race-');
    $originalConnection = DB::getDefaultConnection();
    $processes = [];
    $signals = [$database.'.ready-0', $database.'.ready-1', $database.'.go'];
    config(['database.connections.resource_race' => [
        ...config('database.connections.sqlite'), 'database' => $database, 'url' => null,
    ]]);

    try {
        Artisan::call('migrate', ['--database' => 'resource_race', '--force' => true, '--no-interaction' => true]);
        DB::setDefaultConnection('resource_race');
        [$service, $first, $second, $resource] = portalWithResources(['capacity' => $capacity]);
        $slot = CarbonImmutable::now('Europe/Rome')->addDay()->setTime(9, 0)->utc();
        $services = [$service->id, $service->id];

        if ($capacity > 1) {
            $otherGroup = Service::factory()->for($service->tenant)->create([
                'capacity' => $capacity, 'duration_minutes' => 30, 'slot_interval_minutes' => 30, 'min_notice_minutes' => 0,
            ]);
            $otherGroup->operators()->attach($second);
            $service->operators()->detach($second);
            resourceRequirement($otherGroup, [$resource]);
            $services[1] = $otherGroup->id;
        }

        $worker = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        config(['database.connections.sqlite.database' => $argv[1], 'database.connections.sqlite.url' => null, 'database.default' => 'sqlite']);
        Illuminate\Support\Facades\DB::purge('sqlite');
        Illuminate\Support\Facades\Mail::fake();
        $service = App\Models\Service::query()->findOrFail((int) $argv[2]);
        $operator = App\Models\User::query()->findOrFail((int) $argv[3]);
        file_put_contents($argv[1].'.ready-'.$argv[5], 'ready');
        $deadline = microtime(true) + 10;
        while (! file_exists($argv[1].'.go')) {
            if (microtime(true) > $deadline) { throw new RuntimeException('Booking race barrier timed out'); }
            usleep(10000);
        }
        try {
            $booking = $app->make(App\Actions\Bookings\CreateBooking::class)->handle($service, $operator, Carbon\CarbonImmutable::parse($argv[4]), ['name' => 'Concurrent customer', 'email' => 'race'.$argv[5].'@example.test']);
            echo 'booked';
        } catch (App\Exceptions\SlotUnavailableException) {
            echo 'unavailable';
        }
        PHP;

        foreach ([$first, $second] as $index => $operator) {
            $process = new Process([PHP_BINARY, '-r', $worker, $database, (string) $services[$index], (string) $operator->id, $slot->toIso8601String(), (string) $index], base_path(), ['APP_ENV' => 'testing'], timeout: 15);
            $process->start();
            $processes[] = $process;
        }

        $deadline = microtime(true) + 10;
        while (! file_exists($signals[0]) || ! file_exists($signals[1])) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not reach the booking race barrier');
            }
            usleep(10000);
        }
        file_put_contents($signals[2], 'go');
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
            $results[] = trim($process->getOutput());
        }
        sort($results);

        expect($results)->toBe(['booked', 'unavailable']);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('resource_allocations', 1);
        $this->assertDatabaseCount('customers', 1);
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        DB::setDefaultConnection($originalConnection);
        DB::purge('resource_race');
        foreach ([$database, $database.'-journal', $database.'-wal', $database.'-shm', ...$signals] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
})->with(['individual appointments' => 1, 'different group sessions' => 3]);
