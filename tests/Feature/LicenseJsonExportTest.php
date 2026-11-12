<?php

use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\User;
use App\Support\BillingData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The registration file of a portal for DigitHub License Manager
 * (docs/struttura-json-registrazione-cliente.md): what is in it, what is
 * never in it, and when it is refused.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 10:00:00', 'UTC'));
    $this->admin = User::factory()->admin()->create();
});

/**
 * The customer block of an export as the form that made it would have sent it.
 *
 * @param  array<string, string|null>  $customer
 * @return array<string, string|null>
 */
function formOf(array $customer): array
{
    return [
        'kind' => $customer['kind'], 'company_name' => $customer['kind'] === 'COMPANY' ? $customer['name'] : null,
        'first_name' => $customer['firstName'], 'last_name' => $customer['lastName'], 'contact_name' => $customer['contactName'],
        'tax_code' => $customer['taxCode'], 'vat_number' => $customer['vatNumber'], 'sdi_code' => $customer['sdiCode'], 'pec' => $customer['pec'],
        'street' => $customer['street'], 'postal_code' => $customer['postalCode'], 'city' => $customer['city'], 'province' => $customer['province'],
        'country' => $customer['country'], 'email' => $customer['email'], 'billing_email' => $customer['billingEmail'], 'phone' => $customer['phone'],
    ];
}

test('a complete business is exported with the structure of the registration file', function () {
    $profile = TenantBillingProfile::factory()->create(['pec' => 'rossiservizi@pec.it', 'phone' => '+39 02 1234567']);
    $tenant = $profile->tenant;

    $response = $this->actingAs($this->admin)->get(route('admin.portals.license-json', $tenant))->assertOk();

    expect($response->json())->toBe([
        'schemaVersion' => 1,
        'exportedAt' => '2026-11-12T10:00:00Z',
        'software' => 'TEST_SOFTWARE',
        'tenant' => ['externalId' => $tenant->license_id],
        'customer' => [
            'kind' => 'COMPANY',
            'name' => 'Edil Rossi Srl',
            'firstName' => null,
            'lastName' => null,
            'contactName' => 'Luca Bianchi',
            'taxCode' => '01234567897',
            'vatNumber' => '01234567897',
            'sdiCode' => 'ABC1234',
            'pec' => 'rossiservizi@pec.it',
            'street' => 'Via Garibaldi 10',
            'postalCode' => '20121',
            'city' => 'Milano',
            'province' => 'MI',
            'country' => 'IT',
            'email' => 'info@edilrossi.example',
            'billingEmail' => 'amministrazione@edilrossi.example',
            'phone' => '+39 02 1234567',
        ],
    ]);
});

test('a private person is exported with first and last name and the company fields empty', function () {
    $profile = TenantBillingProfile::factory()->person()->create();

    $customer = $this->actingAs($this->admin)->get(route('admin.portals.license-json', $profile->tenant))->json('customer');

    expect($customer)->toMatchArray([
        'kind' => 'PERSON', 'name' => 'Mario Rossi', 'firstName' => 'Mario', 'lastName' => 'Rossi', 'taxCode' => 'RSSMRA85T10A562S',
        'contactName' => null, 'vatNumber' => null, 'sdiCode' => null, 'pec' => null, 'billingEmail' => null, 'phone' => null,
    ]);
});

test('a business abroad is exported without the Italian fields', function () {
    $profile = TenantBillingProfile::factory()->foreign()->create();

    $customer = $this->actingAs($this->admin)->get(route('admin.portals.license-json', $profile->tenant))->json('customer');

    expect($customer)->toMatchArray(['country' => 'DE', 'vatNumber' => 'DE123456789', 'taxCode' => null, 'sdiCode' => null, 'province' => null]);
});

test('every customer field is always there and empty ones are null, never empty text', function () {
    $profile = TenantBillingProfile::factory()->person()->create();

    $response = $this->actingAs($this->admin)->get(route('admin.portals.license-json', $profile->tenant));
    $customer = $response->json('customer');

    expect(array_keys($customer))->toBe(['kind', 'name', 'firstName', 'lastName', 'contactName', 'taxCode', 'vatNumber', 'sdiCode', 'pec', 'street', 'postalCode', 'city', 'province', 'country', 'email', 'billingEmail', 'phone'])
        ->and(collect($customer)->filter(fn ($value) => $value === ''))->toBeEmpty();
});

test('a field stored as empty or blank text still comes out as null', function () {
    $profile = TenantBillingProfile::factory()->create(['phone' => '', 'billing_email' => '   ', 'contact_name' => '']);

    $customer = $this->actingAs($this->admin)->get(route('admin.portals.license-json', $profile->tenant))->json('customer');

    expect($customer['phone'])->toBeNull()->and($customer['billingEmail'])->toBeNull()->and($customer['contactName'])->toBeNull();
});

test('the file is a download named after the portal, and nothing may keep a copy', function () {
    $profile = TenantBillingProfile::factory()->create();

    $response = $this->actingAs($this->admin)->get(route('admin.portals.license-json', $profile->tenant));

    expect($response->headers->get('Content-Disposition'))->toBe('attachment; filename="'.$profile->tenant->slug.'-license-manager.json"')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Content-Type'))->toContain('application/json');
});

test('nothing else goes in it: no key, no plan, no manager, no portal customers', function () {
    $plan = Plan::factory()->create(['name' => 'PianoSegreto']);
    $profile = TenantBillingProfile::factory()->create();
    $profile->tenant->update(['plan_id' => $plan->id]);
    $manager = User::factory()->manager($profile->tenant)->create(['email' => 'manager.riservato@example.test']);

    $response = $this->actingAs($this->admin)->get(route('admin.portals.license-json', $profile->tenant));
    $body = $response->getContent();

    expect(array_keys($response->json()))->toBe(['schemaVersion', 'exportedAt', 'software', 'tenant', 'customer'])
        ->and($body)->not->toContain('lk_test_key')->not->toContain('PianoSegreto')->not->toContain($manager->email)->not->toContain($profile->tenant->slug);
});

test('what is exported passes the same rules the billing form applies, and comes out of them unchanged', function (string $state) {
    $factory = TenantBillingProfile::factory();
    $profile = ($state === 'company' ? $factory : $factory->{$state}())->create();

    $customer = $this->actingAs($this->admin)->get(route('admin.portals.license-json', $profile->tenant))->json('customer');
    $billing = BillingData::normalise(formOf($customer));
    $errors = Validator::make(['billing' => $billing], BillingData::rules($billing))->errors()->all();

    expect($errors)->toBe([])
        ->and(BillingData::columns($billing)['tax_code'])->toBe($customer['taxCode'])
        ->and(BillingData::columns($billing)['vat_number'])->toBe($customer['vatNumber'])
        ->and(BillingData::columns($billing)['name'])->toBe($customer['name']);
})->with(['company', 'person', 'foreign']);

test('a portal with incomplete or no billing details is refused, and the toast says what is missing', function (string $case) {
    $tenant = $case === 'none' ? Tenant::factory()->create() : TenantBillingProfile::factory()->incomplete()->create()->tenant;

    $this->actingAs($this->admin)
        ->from(route('admin.portals'))
        ->get(route('admin.portals.license-json', $tenant))
        ->assertRedirect(route('admin.portals'))
        ->assertInertiaFlash('toast.message', $case === 'none'
            ? 'Complete the billing details of this portal first: missing billing type.'
            : 'Complete the billing details of this portal first: missing vat number, SDI code or PEC, street address, postal code, province.');

    expect(ActivityLog::query()->where('action', 'portal.license_json_exported')->exists())->toBeFalse();
})->with(['none', 'incomplete']);

test('it is refused while the software code of this environment is not set', function () {
    config(['services.license.software' => '']);
    $profile = TenantBillingProfile::factory()->create();

    $this->actingAs($this->admin)
        ->from(route('admin.portals'))
        ->get(route('admin.portals.license-json', $profile->tenant))
        ->assertRedirect(route('admin.portals'))
        ->assertInertiaFlash('toast.message', 'The software code of the License Manager is not set (LICENSE_SOFTWARE_CODE in the .env file).');
});

test('every download is written in the platform register, without values', function () {
    $profile = TenantBillingProfile::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.portals.license-json', $profile->tenant))->assertOk();

    $log = ActivityLog::query()->where('action', 'portal.license_json_exported')->sole();

    expect($log->tenant_id)->toBeNull()
        ->and($log->context)->toBe(['portal_id' => $profile->tenant->id])
        ->and($log->subject_label)->toBe($profile->tenant->name)
        ->and(json_encode($log->getAttributes()))->not->toContain('Edil Rossi')->not->toContain('01234567897');
});

test('only the administrator can download it', function () {
    $profile = TenantBillingProfile::factory()->create();
    $manager = User::factory()->manager($profile->tenant)->create();

    $this->get(route('admin.portals.license-json', $profile->tenant))->assertRedirect(route('login'));
    $this->actingAs($manager)->get(route('admin.portals.license-json', $profile->tenant))->assertForbidden();

    expect(ActivityLog::query()->where('action', 'portal.license_json_exported')->exists())->toBeFalse();
});

test('the portal list says which portals can be exported and which cannot, and why', function () {
    $ready = TenantBillingProfile::factory()->create();
    $incomplete = TenantBillingProfile::factory()->incomplete()->create();
    $none = Tenant::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.portals'))->assertInertia(fn (Assert $page) => $page
        ->where('licenseSoftwareSet', true)
        ->where('portals.data', function ($portals) use ($ready, $incomplete, $none) {
            $gaps = fn (Tenant $tenant) => collect($portals)->firstWhere('id', $tenant->id)['json_gaps'];

            return $gaps($ready->tenant) === []
                && $gaps($incomplete->tenant) === ['vat number', 'SDI code or PEC', 'street address', 'postal code', 'province']
                && $gaps($none) === ['billing type'];
        }));

    config(['services.license.software' => '']);

    $this->get(route('admin.portals'))->assertInertia(fn (Assert $page) => $page->where('licenseSoftwareSet', false));
});
