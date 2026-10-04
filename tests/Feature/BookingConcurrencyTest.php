<?php

use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Run the callback on a migrated SQLite file that uses the production connection
 * settings. Separate processes can share a file; they cannot share the
 * in-memory database of the test suite.
 *
 * @template TReturn
 *
 * @param  Closure(string): TReturn  $callback
 * @return TReturn
 */
function onSqliteFile(Closure $callback): mixed
{
    $database = tempnam(sys_get_temp_dir(), 'booking-race-');
    $originalConnection = DB::getDefaultConnection();
    config(['database.connections.booking_race' => [
        ...config('database.connections.sqlite'), 'database' => $database, 'url' => null,
    ]]);

    try {
        Artisan::call('migrate', ['--database' => 'booking_race', '--force' => true, '--no-interaction' => true]);
        DB::setDefaultConnection('booking_race');

        return $callback($database);
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge('booking_race');
        foreach ([$database, $database.'-journal', $database.'-wal', $database.'-shm'] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}

/**
 * A portal whose operators are all free tomorrow at 09:00, its local time.
 *
 * @return array{Service, Collection<int, User>, CarbonImmutable}
 */
function contestedSlot(int $operatorCount): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $service = Service::factory()->for($tenant)->create([
        'duration_minutes' => 30, 'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0, 'max_advance_days' => 60, 'requires_confirmation' => false,
    ]);
    $operators = collect(range(1, $operatorCount))->map(function () use ($service): User {
        $operator = User::factory()->operator($service->tenant)->create();
        $service->operators()->attach($operator);
        foreach (range(0, 6) as $weekday) {
            $operator->availabilityRules()->create(['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '13:00']);
        }

        return $operator;
    });

    return [$service, $operators, CarbonImmutable::now('Europe/Rome')->addDay()->setTime(9, 0)->utc()];
}

/**
 * Release one booking attempt per process at the same instant.
 *
 * A worker that fails for any other reason than a taken slot, such as a
 * "database is locked" error, fails the test with its error output.
 *
 * @param  list<User|null>  $operators  one attempt each; null leaves the choice to the portal
 * @return list<string> 'booked' or 'unavailable' for each attempt, sorted
 */
function raceForSlot(string $database, Service $service, array $operators, CarbonImmutable $slot): array
{
    $worker = <<<'PHP'
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config(['database.connections.sqlite.database' => $argv[1], 'database.connections.sqlite.url' => null, 'database.default' => 'sqlite']);
    Illuminate\Support\Facades\DB::purge('sqlite');
    Illuminate\Support\Facades\Mail::fake();
    $service = App\Models\Service::query()->findOrFail((int) $argv[2]);
    $operator = $argv[3] === '0' ? null : App\Models\User::query()->findOrFail((int) $argv[3]);
    file_put_contents($argv[1].'.ready-'.$argv[5], 'ready');
    $deadline = microtime(true) + 20;
    while (! file_exists($argv[1].'.go')) {
        if (microtime(true) > $deadline) { throw new RuntimeException('Booking race barrier timed out'); }
        usleep(5000);
    }
    try {
        $app->make(App\Actions\Bookings\CreateBooking::class)->handle($service, $operator, Carbon\CarbonImmutable::parse($argv[4]), ['name' => 'Concurrent customer', 'email' => 'race'.$argv[5].'@example.test']);
        echo 'booked';
    } catch (App\Exceptions\SlotUnavailableException) {
        echo 'unavailable';
    }
    PHP;

    $processes = [];
    $signals = [$database.'.go'];

    try {
        foreach ($operators as $index => $operator) {
            $signals[] = $database.'.ready-'.$index;
            $process = new Process([PHP_BINARY, '-r', $worker, $database, (string) $service->id, (string) ($operator?->id ?? 0), $slot->toIso8601String(), (string) $index], base_path(), ['APP_ENV' => 'testing'], timeout: 30);
            $process->start();
            $processes[] = $process;
        }

        $deadline = microtime(true) + 20;
        while (collect($signals)->skip(1)->contains(fn (string $signal) => ! file_exists($signal))) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers did not reach the booking race barrier');
            }
            usleep(5000);
        }
        file_put_contents($signals[0], 'go');

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
            $results[] = trim($process->getOutput());
        }
        sort($results);

        return $results;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        foreach ($signals as $signal) {
            if (file_exists($signal)) {
                unlink($signal);
            }
        }
    }
}

test('simultaneous requests for the same operator and slot book it only once', function () {
    onSqliteFile(function (string $database) {
        [$service, $operators, $slot] = contestedSlot(1);

        $results = raceForSlot($database, $service, [$operators[0], $operators[0], $operators[0]], $slot);

        expect($results)->toBe(['booked', 'unavailable', 'unavailable']);
        expect(Booking::query()->where('starts_at', $slot)->count())->toBe(1);
    });
});

test('simultaneous requests without a chosen operator give each free operator the slot once', function () {
    onSqliteFile(function (string $database) {
        [$service, $operators, $slot] = contestedSlot(2);

        $results = raceForSlot($database, $service, [null, null, null], $slot);

        expect($results)->toBe(['booked', 'booked', 'unavailable']);
        expect(Booking::query()->where('starts_at', $slot)->pluck('user_id')->sort()->values()->all())
            ->toBe($operators->pluck('id')->all());
    });
});

test('a database file runs in write-ahead logging mode so reads do not wait for writes', function () {
    onSqliteFile(function () {
        expect(DB::selectOne('pragma journal_mode')->journal_mode)->toBe('wal');
    });
});
