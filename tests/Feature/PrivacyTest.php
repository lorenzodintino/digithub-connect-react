<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'timezone' => 'Europe/Rome',
        'privacy_policy_url' => 'https://studiorossi.test/privacy',
        'terms_url' => 'https://studiorossi.test/termini',
    ]);

    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();

    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
    ]);

    $this->service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
        ]);
    }
});

/**
 * A slot that is free tomorrow on the test service.
 */
function privacySlot(): CarbonImmutable
{
    $tomorrow = CarbonImmutable::now(test()->tenant->timezone)->addDay()->startOfDay();

    return (new SlotFinder(test()->service, test()->operator))->slotsForDay($tomorrow)[0];
}

/**
 * A booking that ended the given number of days ago.
 */
function bookingEndedDaysAgo(int $days, array $attributes = []): Booking
{
    $startsAt = CarbonImmutable::now('UTC')->subDays($days)->setTime(10, 0);

    return Booking::factory()
        ->forSlot(test()->service, test()->operator, $startsAt)
        ->create($attributes);
}

test('a booking cannot be placed without accepting the privacy notice', function () {
    $slot = privacySlot();

    $this->post(route('portal.service.book', [$this->tenant, $this->service]), bookingForm($slot, ['privacy' => false]))
        ->assertSessionHasErrors('privacy');

    expect(Booking::query()->count())->toBe(0);
});

test('the consent is recorded together with the document it covered', function () {
    $slot = privacySlot();

    $this->post(route('portal.service.book', [$this->tenant, $this->service]), bookingForm($slot))
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->sole();

    expect($booking->privacy_accepted_at)->not->toBeNull()
        ->and($booking->privacy_accepted_url)->toBe('https://studiorossi.test/privacy');
});

test('the public page links the documents the portal published', function () {
    $this->get(route('portal.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('portal.privacy_url', 'https://studiorossi.test/privacy')
            ->where('portal.terms_url', 'https://studiorossi.test/termini'));
});

/**
 * Everything the portal settings form sends, with the given changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function portalSettingsForm(Tenant $tenant, array $changes = []): array
{
    return [
        ...$tenant->only(['name', 'description', 'brand_color', 'timezone', 'locale', 'contact_email', 'contact_phone', 'website', 'address', 'privacy_policy_url', 'terms_url', 'data_controller', 'data_retention_days']),
        ...$changes,
    ];
}

test('a manager sets the legal links and the retention', function () {
    $this->actingAs($this->manager)
        ->put(route('app.portal.update'), portalSettingsForm($this->tenant, [
            'privacy_policy_url' => 'https://nuovo.test/privacy',
            'terms_url' => '',
            'data_controller' => 'Studio Rossi SRL',
            'data_retention_days' => '365',
        ]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Portal settings saved.');

    $tenant = $this->tenant->fresh();

    expect($tenant->privacy_policy_url)->toBe('https://nuovo.test/privacy')
        ->and($tenant->terms_url)->toBeNull()
        ->and($tenant->data_controller)->toBe('Studio Rossi SRL')
        ->and($tenant->data_retention_days)->toBe(365);
});

test('the legal links must be real links and the retention a sane number', function () {
    $this->actingAs($this->manager)
        ->put(route('app.portal.update'), portalSettingsForm($this->tenant, ['privacy_policy_url' => 'non-un-link', 'data_retention_days' => '3']))
        ->assertSessionHasErrors(['privacy_policy_url', 'data_retention_days']);
});

test('the retention erases the customers of old bookings and leaves the rest', function () {
    $this->tenant->update(['data_retention_days' => 365]);

    $old = bookingEndedDaysAgo(400, ['customer_name' => 'Vecchio Cliente', 'customer_email' => 'vecchio@example.test']);
    $recent = bookingEndedDaysAgo(30, ['customer_name' => 'Cliente Recente', 'customer_email' => 'recente@example.test']);

    $this->artisan('bookings:anonymise-old')->assertSuccessful();

    expect($old->fresh()->isAnonymised())->toBeTrue()
        ->and($old->fresh()->customer_name)->toBe('')
        ->and($old->fresh()->customer_email)->toBe('')
        ->and($old->fresh()->displayCustomerName())->toBe('Erased customer')
        ->and($recent->fresh()->isAnonymised())->toBeFalse()
        ->and($recent->fresh()->customer_name)->toBe('Cliente Recente');
});

test('a portal without a retention keeps everything', function () {
    $booking = bookingEndedDaysAgo(2000, ['customer_name' => 'Antico Cliente']);

    $this->artisan('bookings:anonymise-old')->assertSuccessful();

    expect($booking->fresh()->isAnonymised())->toBeFalse();
});

test('a manager erases a customer across every booking they have', function () {
    $first = bookingEndedDaysAgo(20, ['customer_name' => 'Anna Ferrari', 'customer_email' => 'anna@example.test']);
    $second = bookingEndedDaysAgo(10, ['customer_name' => 'Anna Ferrari', 'customer_email' => 'anna@example.test']);
    $other = bookingEndedDaysAgo(10, ['customer_name' => 'Marco Neri', 'customer_email' => 'marco@example.test']);

    $this->actingAs($this->manager)
        ->post(route('app.bookings.anonymise', $first))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Customer data erased from 2 bookings.');

    expect($first->fresh()->isAnonymised())->toBeTrue()
        ->and($second->fresh()->isAnonymised())->toBeTrue()
        ->and($other->fresh()->isAnonymised())->toBeFalse()
        ->and(Booking::query()->count())->toBe(3);
});

test('a customer with an upcoming booking is not erased', function () {
    $past = bookingEndedDaysAgo(10, ['customer_name' => 'Anna Ferrari', 'customer_email' => 'anna@example.test']);

    Booking::factory()
        ->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->addDays(3)->setTime(10, 0))
        ->create(['customer_email' => 'anna@example.test', 'status' => BookingStatus::Confirmed]);

    $this->actingAs($this->manager)
        ->post(route('app.bookings.anonymise', $past))
        ->assertSessionHasErrors(['anonymise' => 'This customer still has one upcoming booking. Cancel it first.']);

    expect($past->fresh()->isAnonymised())->toBeFalse();
});

test('an operator cannot erase a customer', function () {
    $booking = bookingEndedDaysAgo(10, ['customer_email' => 'anna@example.test']);

    $this->actingAs($this->operator)
        ->post(route('app.bookings.anonymise', $booking))
        ->assertForbidden();

    expect($booking->fresh()->isAnonymised())->toBeFalse();
});

/**
 * A real PDF, small enough to travel: this PHP has no GD, but a PDF needs none.
 */
function fakePolicy(string $name = 'privacy.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");
}

test('a portal can upload its privacy policy instead of linking one', function () {
    Storage::fake('public');

    $this->actingAs($this->manager)
        ->post(route('app.portal.files.store', 'privacy'), ['privacy' => fakePolicy()])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Document uploaded.');

    $tenant = $this->tenant->fresh();

    expect($tenant->privacy_policy_path)->toStartWith('legal/'.$tenant->id.'/');
    Storage::disk('public')->assertExists($tenant->privacy_policy_path);

    // The uploaded file wins over the link typed earlier.
    expect($tenant->privacyPolicyLink())->toContain($tenant->privacy_policy_path)
        ->and($tenant->privacyPolicyLink())->not->toBe('https://studiorossi.test/privacy');
});

test('only PDFs are accepted as legal documents', function () {
    Storage::fake('public');

    $this->actingAs($this->manager)
        ->post(route('app.portal.files.store', 'terms'), ['terms' => UploadedFile::fake()->createWithContent('termini.txt', 'ciao')])
        ->assertSessionHasErrors('terms');

    expect($this->tenant->fresh()->terms_path)->toBeNull();
});

test('removing the uploaded policy falls back to the link and deletes the file', function () {
    Storage::fake('public');

    $this->actingAs($this->manager)->post(route('app.portal.files.store', 'privacy'), ['privacy' => fakePolicy()]);

    $path = $this->tenant->fresh()->privacy_policy_path;

    $this->delete(route('app.portal.files.destroy', 'privacy'))->assertInertiaFlash('toast.message', 'Document removed.');

    Storage::disk('public')->assertMissing($path);

    expect($this->tenant->fresh()->privacyPolicyLink())->toBe('https://studiorossi.test/privacy');
});

test('the public page links the uploaded document', function () {
    Storage::fake('public');

    $this->actingAs($this->manager)->post(route('app.portal.files.store', 'privacy'), ['privacy' => fakePolicy()]);

    $this->get(route('portal.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('portal.privacy_url', fn (string $url) => str_ends_with($url, $this->tenant->fresh()->privacy_policy_path)));
});
