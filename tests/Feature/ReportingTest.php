<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

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
 * The content of the file a download route just handed back.
 */
function downloadedCsv(TestResponse $response): string
{
    $response->assertDownload();

    return $response->streamedContent();
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

    $this->actingAs($this->manager)->get(route('app.reports'))->assertInertia(fn (Assert $page) => $page
        ->component('app/reports')
        ->where('totals.bookings', 4)
        ->where('totals.completed', 2)
        ->where('totals.no_shows', 1)
        ->where('totals.cancelled', 1)
        // One no show out of three appointments that reached their day.
        ->where('totals.no_show_rate', '33%')
        ->where('totals.value', 10000)
        ->where('totals.value_label', '€ 100,00'));
});

test('the report splits by service and by operator', function () {
    reportBooking($this->operator, 1, BookingStatus::Completed);
    reportBooking($this->colleague, 2, BookingStatus::NoShow);

    $this->actingAs($this->manager)->get(route('app.reports'))->assertInertia(fn (Assert $page) => $page
        ->has('byService', 1)
        ->where('byService.0.label', 'Prima consulenza')
        ->has('byOperator', 2)
        ->where('byOperator', fn ($rows) => collect($rows)->pluck('label')->sort()->values()->all() === ['Giulia Bianchi', 'Luca Verdi']));
});

test('another period shows other numbers', function () {
    $lastMonth = CarbonImmutable::now('UTC')->startOfMonth()->subMonth()->addDays(3)->setTime(10, 0);

    Booking::factory()->forSlot($this->service, $this->operator, $lastMonth)->create(['status' => BookingStatus::Completed]);
    reportBooking($this->operator, 1, BookingStatus::Completed);

    $this->actingAs($this->manager);
    $counted = fn (array $query, int $bookings) => $this->get(route('app.reports', $query))
        ->assertInertia(fn (Assert $page) => $page->where('totals.bookings', $bookings));

    $counted([], 1);
    $counted(['period' => 'last_month'], 1);
    $counted(['period' => 'last_90_days'], 2);
    // A period the page does not offer falls back to this month.
    $counted(['period' => 'forever'], 1);
});

test('an operator only reports on their own bookings', function () {
    reportBooking($this->operator, 1, BookingStatus::Completed);
    reportBooking($this->colleague, 2, BookingStatus::Completed);

    $this->actingAs($this->operator)->get(route('app.reports'))->assertInertia(fn (Assert $page) => $page
        ->where('seesWholePortal', false)
        ->where('totals.bookings', 1));
});

test('the agenda exports what it is showing', function () {
    reportBooking($this->operator, 1, BookingStatus::Completed, ['customer_name' => 'Anna Ferrari', 'customer_email' => 'anna@example.test']);
    reportBooking($this->colleague, 2, BookingStatus::Completed, ['customer_name' => 'Marco Neri', 'customer_email' => 'marco@example.test']);

    $csv = downloadedCsv($this->actingAs($this->manager)
        ->get(route('app.bookings.export', ['range' => 'all', 'operator' => $this->operator->id])));

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

    $csv = downloadedCsv($this->actingAs($this->manager)
        ->get(route('app.bookings.export', ['range' => 'all'])));

    expect($csv)->toContain('Erased customer')->not->toContain('Anna Ferrari');
});

test('the customer directory exports too', function () {
    Customer::factory()->for($this->tenant)->create(['name' => 'Anna Ferrari', 'email' => 'anna@example.test']);
    Customer::factory()->for($this->tenant)->create(['name' => 'Bruno Neri', 'email' => 'bruno@example.test']);

    $response = $this->actingAs($this->manager)->get(route('app.customers.export', ['q' => 'ferrari']));

    $response->assertDownload();

    expect($response->streamedContent())
        ->toContain('Anna Ferrari')
        ->toContain('anna@example.test')
        ->not->toContain('Bruno Neri');
});

test('last month stays last month on the last day of a long one', function () {
    // The 31st is where month arithmetic slips: a plain subMonth() from here
    // lands on the 3rd of this month, and the report would draw the wrong one.
    $this->travelTo(CarbonImmutable::parse('2026-03-31 12:00', 'UTC'));

    Booking::factory()
        ->forSlot($this->service, $this->operator, CarbonImmutable::parse('2026-02-10 10:00', 'UTC'))
        ->create(['status' => BookingStatus::Completed]);

    Booking::factory()
        ->forSlot($this->service, $this->operator, CarbonImmutable::parse('2026-03-10 10:00', 'UTC'))
        ->create(['status' => BookingStatus::Completed]);

    $this->actingAs($this->manager)->get(route('app.reports', ['period' => 'last_month']))->assertInertia(fn (Assert $page) => $page
        ->where('window.from', '2026-02-01')
        ->where('window.to', '2026-02-28')
        ->where('totals.bookings', 1));
});
