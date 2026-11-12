<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Support\ServiceFormData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * The service form exactly as the React page sends it: a new service of the
 * portal, or an existing one as loaded into the form, with the given changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function serviceForm(Tenant|Service $base, array $changes = []): array
{
    $form = $base instanceof Service ? ServiceFormData::of($base) : ServiceFormData::defaults($base);

    return [...$form, ...$changes];
}

/**
 * The portal form as the page sends it.
 *
 * @return array<string, mixed>
 */
function portalForm(array $overrides = []): array
{
    return [
        'name' => 'Studio Bianchi',
        'slug' => 'studio-bianchi',
        'contact_email' => '',
        'contact_phone' => '',
        'website' => '',
        'address' => '',
        'timezone' => 'Europe/Rome',
        'locale' => 'it',
        'brand_color' => '#4f46e5',
        'plan_id' => '',
        'description' => '',
        'is_active' => true,
        'manager_name' => 'Anna Bianchi',
        'manager_email' => 'anna@example.com',
        ...$overrides,
    ];
}

/**
 * The booking form of a public service page as the page sends it, for the
 * time the visitor picked.
 *
 * @return array<string, mixed>
 */
function bookingForm(CarbonImmutable $slot, array $overrides = []): array
{
    return [
        'name' => 'Mario Bianchi',
        'email' => 'mario@example.com',
        'phone' => '',
        'notes' => '',
        'privacy' => true,
        'answers' => [],
        'slot' => $slot->toIso8601String(),
        'operator' => '',
        'tz' => 'Europe/Rome',
        ...$overrides,
    ];
}

/**
 * The times a public calendar offers on the chosen day, as the visitor reads
 * them: `day` on the service page, `reschedule.day` on the booking page.
 *
 * @return array<int, string>
 */
function offeredTimes(TestResponse $response, string $day = 'day'): array
{
    return collect($response->inertiaProps($day.'.slots') ?? [])->pluck('time')->all();
}
