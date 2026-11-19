<?php

use App\Enums\BillingKind;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\TenantBillingProfile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The billing section of the portal form, from the administrator's side. The
 * matrix of values that pass and fail is in BillingDataTest: here the form is
 * shown to apply those rules, to keep the data, and to keep it from anyone else.
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * The portal form for an existing portal, with the billing section replaced.
 *
 * @param  array<string, string>  $billing
 * @return array<string, mixed>
 */
function updateForm(Tenant $tenant, array $billing): array
{
    return portalForm(['slug' => $tenant->slug, 'name' => $tenant->name, 'manager_name' => '', 'manager_email' => '', 'billing' => $billing]);
}

test('a portal is created with the billing profile of a business', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.portals.store'), portalForm())
        ->assertSessionHasNoErrors();

    $profile = Tenant::query()->where('slug', 'studio-bianchi')->sole()->billingProfile;

    expect($profile->kind)->toBe(BillingKind::Company)
        ->and($profile->name)->toBe('Edil Rossi Srl')
        ->and($profile->vat_number)->toBe('01234567897')
        ->and($profile->sdi_code)->toBe('ABC1234')
        ->and($profile->country)->toBe('IT');
});

test('a portal is created with the billing profile of a private person, typed the way people type', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.portals.store'), portalForm(['billing' => billingForm([
            'kind' => 'PERSON', 'company_name' => '', 'first_name' => ' Mario ', 'last_name' => 'Rossi', 'contact_name' => '',
            'tax_code' => 'rss mra 85t10 a562s', 'vat_number' => '', 'sdi_code' => '', 'street' => 'Via Roma 1', 'postal_code' => '30100',
            'city' => 'Venezia', 'province' => 've', 'country' => '', 'email' => 'MARIO@EXAMPLE.IT',
        ])]))
        ->assertSessionHasNoErrors();

    $profile = Tenant::query()->where('slug', 'studio-bianchi')->sole()->billingProfile;

    expect($profile->kind)->toBe(BillingKind::Person)
        ->and($profile->name)->toBe('Mario Rossi')
        ->and($profile->tax_code)->toBe('RSSMRA85T10A562S')
        ->and($profile->province)->toBe('VE')
        ->and($profile->country)->toBe('IT')
        ->and($profile->email)->toBe('mario@example.it');
});

test('a portal is created with the billing profile of a business abroad', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.portals.store'), portalForm(['billing' => billingForm([
            'country' => 'gb', 'vat_number' => 'GB123456789', 'tax_code' => '', 'sdi_code' => '', 'postal_code' => 'sw1a 1aa',
            'province' => 'Greater London', 'city' => 'London', 'street' => '10 Downing Street',
        ])]))
        ->assertSessionHasNoErrors();

    $profile = Tenant::query()->where('slug', 'studio-bianchi')->sole()->billingProfile;

    expect($profile->country)->toBe('GB')
        ->and($profile->vat_number)->toBe('GB123456789')
        ->and($profile->postal_code)->toBe('SW1A 1AA')
        ->and($profile->province)->toBe('Greater London');
});

test('a mistake in the billing section sends the form back on the field with the typed values, and creates nothing', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.portals'))
        ->post(route('admin.portals.store'), portalForm(['billing' => billingForm(['vat_number' => '01234567890'])]))
        ->assertRedirect(route('admin.portals'))
        ->assertSessionHasErrors(['billing.vat_number' => 'VAT number not valid: it has 11 digits with the correct check digit.'])
        ->assertSessionHasInput('billing.company_name', 'Edil Rossi Srl');

    expect(Tenant::query()->where('slug', 'studio-bianchi')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'anna@example.com')->exists())->toBeFalse()
        ->and(TenantBillingProfile::query()->exists())->toBeFalse();
});

test('a new portal requires billing unless the demo option is selected', function (array $options) {
    $form = portalForm($options);
    unset($form['billing']);

    $this->actingAs($this->admin)
        ->post(route('admin.portals.store'), $form)
        ->assertSessionHasErrors(['billing.kind', 'billing.street', 'billing.email']);

    expect(Tenant::query()->where('slug', 'studio-bianchi')->exists())->toBeFalse();
})->with([
    'demo option omitted' => [[]],
    'demo option off' => [['demo' => false]],
]);

test('a demo portal is created with a manager and a license ID without saving billing details', function (array $submitted) {
    $form = portalForm(['demo' => true]);
    unset($form['billing']);

    $this->actingAs($this->admin)
        ->post(route('admin.portals.store'), [...$form, ...$submitted])
        ->assertSessionHasNoErrors();

    $portal = Tenant::query()->where('slug', $form['slug'])->sole();

    expect($portal->billingProfile)->toBeNull()
        ->and($portal->manager?->email)->toBe($form['manager_email'])
        ->and($portal->license_id)->not->toBeEmpty()
        ->and($portal->license_exempt)->toBeFalse();
})->with([
    'billing omitted' => [[]],
    'billing still in the form' => [['billing' => ['company_name' => 'Studio Bianchi']]],
]);

test('a demo portal can be given billing details later under the same license ID', function () {
    $form = portalForm(['demo' => true, 'billing' => []]);
    $admin = $this->actingAs($this->admin);
    $admin->post(route('admin.portals.store'), $form)->assertSessionHasNoErrors();
    $portal = Tenant::query()->where('slug', $form['slug'])->sole();

    $admin->put(route('admin.portals.update', $portal), updateForm($portal, billingForm()))
        ->assertSessionHasNoErrors();

    $updatedPortal = $portal->fresh();
    expect($updatedPortal->billingProfile?->name)->toBe('Edil Rossi Srl')
        ->and($updatedPortal->license_id)->toBe($portal->license_id);
});

test('an invalid demo flag prevents portal creation', function () {
    $form = portalForm(['demo' => 'yes', 'billing' => []]);

    $this->actingAs($this->admin)
        ->post(route('admin.portals.store'), $form)
        ->assertSessionHasErrors('demo');

    expect(Tenant::query()->where('slug', $form['slug'])->exists())->toBeFalse();
});

test('a demo flag on update cannot bypass validation of billing details', function () {
    $tenant = Tenant::factory()->create();
    $form = updateForm($tenant, billingForm(['street' => '']));

    $this->actingAs($this->admin)
        ->put(route('admin.portals.update', $tenant), [...$form, 'demo' => true])
        ->assertSessionHasErrors('billing.street');

    expect($tenant->fresh()->billingProfile)->toBeNull();
});

test('a portal from before the billing profile can be saved with the section left empty and stays incomplete', function () {
    $tenant = Tenant::factory()->create();
    $empty = billingForm(array_fill_keys(array_keys(billingForm()), ''));

    $this->actingAs($this->admin)
        ->put(route('admin.portals.update', $tenant), updateForm($tenant, [...$empty, 'country' => 'IT']))
        ->assertSessionHasNoErrors();

    expect($tenant->fresh()->billingProfile)->toBeNull()
        ->and(ActivityLog::query()->where('action', 'portal.billing_updated')->exists())->toBeFalse();
});

test('as soon as one field is filled in the whole section is required', function (array $filled) {
    $tenant = Tenant::factory()->create();
    $empty = billingForm([...array_fill_keys(array_keys(billingForm()), ''), 'country' => 'IT']);

    $this->actingAs($this->admin)
        ->put(route('admin.portals.update', $tenant), updateForm($tenant, [...$empty, ...$filled]))
        ->assertSessionHasErrors(['billing.street', 'billing.city', 'billing.email']);

    expect($tenant->fresh()->billingProfile)->toBeNull();
})->with([
    'only the kind' => [['kind' => 'COMPANY']],
    'only the phone' => [['phone' => '333 1234567']],
]);

test('a portal from before the billing profile gets one when the section is completed', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.portals.update', $tenant), updateForm($tenant, billingForm()))
        ->assertSessionHasNoErrors();

    expect($tenant->fresh()->billingProfile->vat_number)->toBe('01234567897');
});

test('a portal that has a billing profile cannot have it emptied', function () {
    $profile = TenantBillingProfile::factory()->create();
    $tenant = $profile->tenant;
    $empty = billingForm([...array_fill_keys(array_keys(billingForm()), ''), 'country' => 'IT']);

    $this->actingAs($this->admin)
        ->put(route('admin.portals.update', $tenant), updateForm($tenant, $empty))
        ->assertSessionHasErrors(['billing.kind']);

    expect($tenant->fresh()->billingProfile->vat_number)->toBe('01234567897');
});

test('changing the kind of an existing profile clears the fields of the old kind', function () {
    $profile = TenantBillingProfile::factory()->create();
    $tenant = $profile->tenant;

    $this->actingAs($this->admin)
        ->put(route('admin.portals.update', $tenant), updateForm($tenant, billingForm([
            'kind' => 'PERSON', 'first_name' => 'Mario', 'last_name' => 'Rossi', 'tax_code' => 'RSSMRA85T10A562S',
            'company_name' => 'Edil Rossi Srl', 'vat_number' => '01234567897', 'sdi_code' => 'ABC1234', 'contact_name' => 'Luca Bianchi',
        ])))
        ->assertSessionHasNoErrors();

    $profile = $tenant->fresh()->billingProfile;

    expect(TenantBillingProfile::query()->count())->toBe(1)
        ->and($profile->kind)->toBe(BillingKind::Person)
        ->and($profile->name)->toBe('Mario Rossi')
        ->and($profile->vat_number)->toBeNull()
        ->and($profile->sdi_code)->toBeNull()
        ->and($profile->contact_name)->toBeNull();
});

test('the register lists the fields that changed and never their values', function () {
    $this->actingAs($this->admin)->post(route('admin.portals.store'), portalForm())->assertSessionHasNoErrors();
    $tenant = Tenant::query()->where('slug', 'studio-bianchi')->sole();

    $this->put(route('admin.portals.update', $tenant), updateForm($tenant, billingForm(['city' => 'Torino', 'pec' => 'amm@edilrossi.example'])))
        ->assertSessionHasNoErrors();

    $lines = ActivityLog::query()->where('action', 'portal.billing_updated')->oldest('id')->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[1]->context['fields'])->toBe(['pec', 'city'])
        ->and($lines[1]->context['portal_id'])->toBe($tenant->id)
        // The line belongs to the platform: the register of the portal, read by its manager, does not carry it.
        ->and($lines[1]->tenant_id)->toBeNull()
        ->and(json_encode($lines->pluck('context')))->not->toContain('Torino')->not->toContain('01234567897')->not->toContain('edilrossi');
});

test('the portal list carries the billing values of each portal for the edit form', function () {
    $billed = TenantBillingProfile::factory()->person()->create();
    $legacy = Tenant::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.portals'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/portals')
            ->where('portals.data', fn ($portals) => collect($portals)->firstWhere('id', $billed->tenant_id)['form']['billing']['tax_code'] === 'RSSMRA85T10A562S'
                && collect($portals)->firstWhere('id', $billed->tenant_id)['form']['billing']['kind'] === 'PERSON'
                && collect($portals)->firstWhere('id', $legacy->id)['form']['billing']['kind'] === ''
                && collect($portals)->firstWhere('id', $legacy->id)['form']['billing']['country'] === 'IT'));
});

test('the detail page tells the administrator what is complete, what is missing and what to copy', function () {
    $complete = TenantBillingProfile::factory()->create();
    $incomplete = TenantBillingProfile::factory()->incomplete()->create();
    $legacy = Tenant::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.portals.show', $complete->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/portal')
            ->where('billing.tenant_id', $complete->tenant->license_id)
            ->where('billing.complete', true)
            ->where('billing.gaps', [])
            ->where('billing.kind', 'Business')
            ->where('billing.values.vat_number', '01234567897')
            ->where('billing.text', fn (string $text) => str_starts_with($text, "Edil Rossi Srl\nPartita IVA: 01234567897\n") && str_ends_with($text, 'Tenant id (License Manager): '.$complete->tenant->license_id)));

    $this->get(route('admin.portals.show', $incomplete->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('billing.complete', false)
            ->where('billing.gaps', ['vat number', 'SDI code or PEC', 'street address', 'postal code', 'province']));

    $this->get(route('admin.portals.show', $legacy))
        ->assertInertia(fn (Assert $page) => $page
            ->where('billing.complete', false)
            ->where('billing.gaps', ['billing type'])
            ->where('billing.kind', null)
            ->where('billing.text', 'Tenant id (License Manager): '.$legacy->license_id));
});

test('only the administrator manages billing', function () {
    $tenant = Tenant::factory()->create();

    expect(Gate::forUser($this->admin)->allows('manageBilling', $tenant))->toBeTrue()
        ->and(Gate::forUser(User::factory()->manager($tenant)->create())->allows('manageBilling', $tenant))->toBeFalse()
        ->and(Gate::forUser(User::factory()->operator($tenant)->create())->allows('manageBilling', $tenant))->toBeFalse();
});

test('a manager cannot read or write the billing profile of their own portal through the admin pages', function () {
    $profile = TenantBillingProfile::factory()->create();
    $manager = User::factory()->manager($profile->tenant)->create();

    $this->actingAs($manager)->get(route('admin.portals.show', $profile->tenant))->assertForbidden();
    $this->put(route('admin.portals.update', $profile->tenant), updateForm($profile->tenant, billingForm(['city' => 'Torino'])))->assertForbidden();

    expect($profile->fresh()->city)->toBe('Milano');
});

test('the billing profile reaches nobody but the administrator', function () {
    $profile = TenantBillingProfile::factory()->create(['name' => 'Fatturazione Segreta Srl', 'pec' => 'riservata@pec.example', 'vat_number' => '01234567897']);
    $tenant = $profile->tenant;
    $manager = User::factory()->manager($tenant)->create();
    $secrets = ['Fatturazione Segreta', 'riservata@pec.example', 'edilrossi.example', 'Via Garibaldi'];

    $pages = [
        $this->actingAs($manager)->get(route('app.portal')),
        $this->get(route('app.activity')),
        $this->get(route('dashboard')),
        $this->get(route('portal.show', $tenant)),
    ];

    foreach ($pages as $page) {
        $page->assertOk();

        foreach ($secrets as $secret) {
            $page->assertDontSee($secret, false);
        }
    }

    $export = $this->get(route('app.portal.export'));
    $zip = new ZipArchive;
    $zip->open($export->baseResponse->getFile()->getPathname());
    $contents = '';
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $contents .= $zip->getNameIndex($index).$zip->getFromIndex($index);
    }
    $zip->close();

    foreach ($secrets as $secret) {
        expect($contents)->not->toContain($secret);
    }
    expect($contents)->not->toContain('tenant_billing_profiles');
});

test('deleting a portal deletes its billing profile', function () {
    $profile = TenantBillingProfile::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.portals.destroy', $profile->tenant))->assertRedirect(route('admin.portals'));

    $this->assertDatabaseMissing('tenant_billing_profiles', ['id' => $profile->id]);
});
