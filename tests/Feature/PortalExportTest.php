<?php

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'studio-rossi', 'mail_username' => 'smtp-user', 'mail_password' => 'smtp-secret']);
    $this->manager = User::factory()->manager($this->tenant)->create();
});

/**
 * Open the archive a download response carries, as entry name => contents.
 *
 * @return array<string, string>
 */
function exportedEntries(TestResponse $response): array
{
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());

    $entries = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entries[$zip->getNameIndex($index)] = $zip->getFromIndex($index);
    }
    $zip->close();

    return $entries;
}

test('the manager downloads every record of the portal with notes readable', function () {
    $service = Service::factory()->for($this->tenant)->create(['name' => 'Prima consulenza']);
    Customer::factory()->for($this->tenant)->create(['email' => 'anna@example.test', 'notes' => 'Paga in contanti']);
    $booking = Booking::factory()->for($service)->create(['user_id' => $this->manager->id, 'customer_notes' => 'Arrivo in ritardo']);
    $booking->answers()->create(['label' => 'Allergie', 'value' => 'Lattice', 'position' => 0]);

    $response = $this->actingAs($this->manager)->get(route('app.portal.export'));

    $response->assertOk()->assertDownload('studio-rossi-export-'.now()->format('Y-m-d').'.zip');
    $entries = exportedEntries($response);
    expect(json_decode($entries['services.json'], true)[0]['name'])->toBe('Prima consulenza');
    expect(json_decode($entries['customers.json'], true)[0]['notes'])->toBe('Paga in contanti');
    expect(json_decode($entries['bookings.json'], true)[0]['customer_notes'])->toBe('Arrivo in ritardo');
    expect(json_decode($entries['booking-answers.json'], true)[0]['value'])->toBe('Lattice');
    expect(json_decode($entries['export.json'], true)['records'])->toMatchArray(['services' => 1, 'customers' => 1, 'bookings' => 1, 'booking-answers' => 1, 'staff' => 1]);
});

test('the export leaves out passwords, two-factor secrets and SMTP credentials', function () {
    $this->manager->forceFill(['two_factor_secret' => 'totp-secret', 'two_factor_recovery_codes' => 'recovery-codes'])->save();

    $entries = exportedEntries($this->actingAs($this->manager)->get(route('app.portal.export')));

    $staff = json_decode($entries['staff.json'], true)[0];
    $portal = json_decode($entries['portal.json'], true)[0];
    expect($staff)->toHaveKey('email')->not->toHaveKeys(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token']);
    expect($portal)->toHaveKey('slug')->not->toHaveKeys(['mail_username', 'mail_password']);
    expect(implode('', $entries))->not->toContain('smtp-secret')->not->toContain('totp-secret');
});

test('the export carries nothing of another portal', function () {
    $other = Tenant::factory()->create();
    $otherService = Service::factory()->for($other)->create();
    Customer::factory()->for($other)->create(['email' => 'altro@example.test']);
    Booking::factory()->for($otherService)->create(['user_id' => User::factory()->operator($other)->create()->id, 'customer_email' => 'altro@example.test']);

    $entries = exportedEntries($this->actingAs($this->manager)->get(route('app.portal.export')));

    expect(implode('', $entries))->not->toContain('altro@example.test');
    expect(json_decode($entries['bookings.json'], true))->toBe([]);
});

test('the export includes the files the portal uploaded', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logos/'.$this->tenant->id.'/logo.png', 'logo-bytes');
    Storage::disk('public')->put('legal/'.$this->tenant->id.'/privacy.pdf', 'privacy-bytes');
    Storage::disk('public')->put('avatars/'.$this->manager->id.'.webp', 'avatar-bytes');
    $this->tenant->update(['logo_path' => 'logos/'.$this->tenant->id.'/logo.png', 'privacy_policy_path' => 'legal/'.$this->tenant->id.'/privacy.pdf']);
    $this->manager->update(['avatar_path' => 'avatars/'.$this->manager->id.'.webp']);

    $entries = exportedEntries($this->actingAs($this->manager)->get(route('app.portal.export')));

    expect($entries)->toMatchArray([
        'files/logo.png' => 'logo-bytes',
        'files/privacy-policy.pdf' => 'privacy-bytes',
        'files/avatars/'.$this->manager->id.'.webp' => 'avatar-bytes',
    ]);
});

test('an export is written to the register', function () {
    $this->actingAs($this->manager)->get(route('app.portal.export'))->assertOk();

    expect(ActivityLog::query()->where('action', 'portal.exported')->sole())
        ->tenant_id->toBe($this->tenant->id)
        ->user_id->toBe($this->manager->id);
});

test('only the manager of the portal may export it', function (string $role) {
    $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->operator($this->tenant)->create();

    $this->actingAs($user)->get(route('app.portal.export'))->assertForbidden();

    expect(ActivityLog::query()->where('action', 'portal.exported')->exists())->toBeFalse();
})->with([
    'an operator' => 'operator',
    'the platform administrator' => 'admin',
]);

test('a guest is sent to the login page', function () {
    $this->get(route('app.portal.export'))->assertRedirect(route('login'));
});
