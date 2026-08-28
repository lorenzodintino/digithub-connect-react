<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome', 'name' => 'Studio Rossi']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create(['name' => 'Giulia Bianchi']);
    $this->colleague = User::factory()->operator($this->tenant)->create(['name' => 'Luca Verdi']);

    $this->service = Service::factory()->for($this->tenant)->create([
        'name' => 'Prima consulenza',
        'price_cents' => 5000,
        'duration_minutes' => 30,
    ]);
});

/**
 * The content of the file a Livewire component just handed back.
 */
function downloadedCsv(Testable $component): string
{
    $component->assertFileDownloaded();

    return base64_decode(data_get($component->effects, 'download.content'));
}

/**
 * A booking of this month, on the given day of it.
 */
function reportBooking(User $operator, int $dayOfMonth, BookingStatus $status, array $attributes = []): Booking
{
    $day = CarbonImmutable::now('UTC')->startOfMonth()->addDays($dayOfMonth - 1)->setTime(10, 0);

    return Booking::factory()
        ->forSlot(test()->service, $operator, $day)
        ->create(['status' => $status, 'price_cents' => 5000, ...$attributes]);
}

test('the report counts the period and works out the no show rate', function () {
    reportBooking($this->operator, 1, BookingStatus::Completed);
    reportBooking($this->operator, 2, BookingStatus::Completed);
    reportBooking($this->colleague, 3, BookingStatus::NoShow);
    reportBooking($this->colleague, 4, BookingStatus::Cancelled);

    $component = Livewire::actingAs($this->manager)->test('pages::app.reports');

    $totals = $component->get('totals');

    expect($totals['bookings'])->toBe(4)
        ->and($totals['completed'])->toBe(2)
        ->and($totals['no_shows'])->toBe(1)
        ->and($totals['cancelled'])->toBe(1)
        // One no show out of three appointments that reached their day.
        ->and($totals['no_show_rate'])->toBe('33%')
        ->and($totals['value'])->toBe(10000);
});

test('the report splits by service and by operator', function () {
    reportBooking($this->operator, 1, BookingStatus::Completed);
    reportBooking($this->colleague, 2, BookingStatus::NoShow);

    $component = Livewire::actingAs($this->manager)->test('pages::app.reports');

    expect($component->get('byService'))->toHaveCount(1)
        ->and($component->get('byOperator'))->toHaveCount(2);

    $component->assertSee('Giulia Bianchi')->assertSee('Luca Verdi')->assertSee('Prima consulenza');
});

test('another period shows other numbers', function () {
    $lastMonth = CarbonImmutable::now('UTC')->subMonth()->startOfMonth()->addDays(3)->setTime(10, 0);

    Booking::factory()->forSlot($this->service, $this->operator, $lastMonth)->create(['status' => BookingStatus::Completed]);
    reportBooking($this->operator, 1, BookingStatus::Completed);

    $component = Livewire::actingAs($this->manager)->test('pages::app.reports');

    expect($component->get('totals')['bookings'])->toBe(1);

    $component->set('period', 'last_month');

    expect($component->get('totals')['bookings'])->toBe(1);

    $component->set('period', 'last_90_days');

    expect($component->get('totals')['bookings'])->toBe(2);
});

test('an operator only reports on their own bookings', function () {
    reportBooking($this->operator, 1, BookingStatus::Completed);
    reportBooking($this->colleague, 2, BookingStatus::Completed);

    $component = Livewire::actingAs($this->operator)->test('pages::app.reports');

    expect($component->get('totals')['bookings'])->toBe(1);
});

test('the agenda exports what it is showing', function () {
    reportBooking($this->operator, 1, BookingStatus::Completed, ['customer_name' => 'Anna Ferrari', 'customer_email' => 'anna@example.test']);
    reportBooking($this->colleague, 2, BookingStatus::Completed, ['customer_name' => 'Marco Neri', 'customer_email' => 'marco@example.test']);

    $csv = downloadedCsv(Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('range', 'all')
        ->set('operator', (string) $this->operator->id)
        ->call('exportCsv'));

    expect($csv)->toContain('Anna Ferrari')
        ->not->toContain('Marco Neri')
        // Semicolons and a BOM, or Italian Excel makes a mess of it.
        ->and($csv)->toStartWith("\u{FEFF}")
        // PHP quotes any field holding a space, hence the quotes here.
        ->and($csv)->toContain(';"Prima consulenza";');
});

test('an erased customer stays erased in the export', function () {
    $booking = reportBooking($this->operator, 1, BookingStatus::Completed, ['customer_name' => 'Anna Ferrari']);
    $booking->update(['customer_name' => '', 'customer_email' => '', 'anonymised_at' => now()]);

    $csv = downloadedCsv(Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->set('range', 'all')
        ->call('exportCsv'));

    expect($csv)->toContain('Erased customer')->not->toContain('Anna Ferrari');
});

test('the customer directory exports too', function () {
    Customer::factory()->for($this->tenant)->create(['name' => 'Anna Ferrari', 'email' => 'anna@example.test']);

    $csv = downloadedCsv(Livewire::actingAs($this->manager)
        ->test('pages::app.customers')
        ->call('exportCsv'));

    expect($csv)->toContain('Anna Ferrari')->toContain('anna@example.test');
});
