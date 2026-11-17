<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Support\ServiceFormData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
    // The real License Manager is never called from a test: whatever is not faked fails.
    ->beforeEach(fn () => Http::preventStrayRequests())
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
 * The billing section of the portal form as the page sends it: a complete
 * Italian business, every field present even when empty.
 *
 * @param  array<string, string>  $changes
 * @return array<string, string>
 */
function billingForm(array $changes = []): array
{
    return [
        'kind' => 'COMPANY',
        'company_name' => 'Edil Rossi Srl',
        'first_name' => '',
        'last_name' => '',
        'contact_name' => 'Luca Bianchi',
        'tax_code' => '01234567897',
        'vat_number' => '01234567897',
        'sdi_code' => 'ABC1234',
        'pec' => '',
        'street' => 'Via Garibaldi 10',
        'postal_code' => '20121',
        'city' => 'Milano',
        'province' => 'MI',
        'country' => 'IT',
        'email' => 'info@edilrossi.example',
        'billing_email' => '',
        'phone' => '',
        ...$changes,
    ];
}

/**
 * The portal form as the page sends it, with a complete billing section.
 *
 * @return array<string, mixed>
 */
function portalForm(array $overrides = []): array
{
    return [
        'billing' => billingForm(),
        'name' => 'Studio Bianchi',
        'slug' => 'studio-bianchi',
        'contact_email' => '',
        'contact_phone' => '',
        'website' => '',
        'address' => '',
        'timezone' => 'Europe/Rome',
        'locale' => 'it',
        'brand_color' => '#4f46e5',
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

/**
 * The 200 answer of DigitHub License Manager for a tenant, in the shape of the
 * contract (docs/guida-integrazione-licenze.md, §4.2), customer and payments included:
 * the platform must keep neither.
 *
 * @param  array<string, mixed>  $access
 * @param  array<string, mixed>  $license
 * @return array<string, mixed>
 */
function licenseAnswer(array $access = [], array $license = []): array
{
    return ['data' => [
        'checkedAt' => now()->toIso8601String(),
        'access' => [
            'allowed' => true,
            'reason' => 'active',
            'state' => 'ACTIVE',
            'expiresAt' => now()->addYear()->toIso8601String(),
            'graceEndsAt' => now()->addYear()->addDays(7)->toIso8601String(),
            'graceDays' => 7,
            ...$access,
        ],
        'license' => [
            'id' => 'lic_1',
            'status' => 'ACTIVE',
            'startsAt' => now()->subMonth()->toIso8601String(),
            'expiresAt' => now()->addYear()->toIso8601String(),
            'lifetime' => false,
            'planType' => ['id' => 'pt_1', 'code' => 'pro', 'name' => 'Pro', 'attributes' => [
                'max_servizi' => 15, 'max_operatori' => 5, 'max_prenotazioni_mese' => 1000, 'max_lista_attesa' => 5, 'max_pacchetti' => 3,
                'is_risorse' => true, 'is_lista_attesa' => true, 'is_pacchetti' => true, 'is_servizi_gruppo' => false,
                'is_pulsanti_calendario' => true, 'is_embed_link' => true, 'is_custom_smtp' => true,
            ], 'prices' => [
                ['months' => 12, 'priceCents' => 35000, 'currency' => 'EUR', 'active' => true],
                ['months' => 1, 'priceCents' => 3500, 'currency' => 'EUR', 'active' => true],
                ['months' => null, 'priceCents' => 120000, 'currency' => 'EUR', 'active' => false],
            ]],
            ...$license,
        ],
        'periods' => [],
        'balance' => ['dueCents' => 35000, 'paidCents' => 20000, 'outstandingCents' => 15000, 'currency' => 'EUR'],
        'payments' => [['id' => 'pay_1', 'amountCents' => 20000, 'currency' => 'EUR', 'paidAt' => now()->toIso8601String(), 'note' => 'Bonifico segreto', 'voidedAt' => null, 'voidReason' => null, 'createdAt' => now()->toIso8601String()]],
        'customer' => ['id' => 'cus_1', 'kind' => 'COMPANY', 'name' => 'Cliente Riservato Srl', 'email' => 'riservato@cliente.example', 'vatNumber' => '01234567897'],
        'tenant' => ['id' => 'ten_1', 'externalId' => '1', 'archived' => false],
        'software' => ['id' => 'sw_1', 'code' => 'TEST_SOFTWARE', 'name' => 'Test', 'status' => 'ACTIVE', 'graceDays' => 7],
    ]];
}
