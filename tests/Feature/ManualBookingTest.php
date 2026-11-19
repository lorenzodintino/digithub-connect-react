<?php

use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\QuestionType;
use App\Mail\Customer\BookingConfirmedMail;
use App\Mail\Customer\BookingPendingMail;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\Service;
use App\Models\ServiceResourceRequirement;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{Service, User, User, User} */
function manualBookingSetup(array $serviceOptions = []): array
{
    $tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $manager = User::factory()->manager($tenant)->create();
    $first = User::factory()->operator($tenant)->create(['name' => 'Anna']);
    $second = User::factory()->operator($tenant)->create(['name' => 'Bruno']);
    $service = Service::factory()->for($tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 120,
        ...$serviceOptions,
    ]);
    $service->operators()->attach([$first->id, $second->id]);

    foreach ([$first, $second] as $operator) {
        $operator->availabilityRules()->create(['weekday' => 4, 'starts_at' => '09:00:00', 'ends_at' => '12:00:00']);
    }

    return [$service, $manager, $first, $second];
}

function manualBookingData(string $slot = '2026-11-19T09:00:00+00:00'): array
{
    return ['name' => 'Mario Rossi', 'email' => 'mario@example.test', 'phone' => '+3901234', 'slot' => $slot];
}

test('the manager can choose the operator and book with short notice from the agenda', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-19 08:50 UTC'));
    Mail::fake();
    [$service, $manager, , $second] = manualBookingSetup();
    $question = $service->questions()->create(['type' => QuestionType::ShortText, 'label' => 'Reason for visit', 'is_required' => true, 'position' => 0]);
    $portal = $service->tenant;

    $this->actingAs($manager)->get(route('app.bookings.create'))->assertInertia(fn (Assert $page) => $page
        ->component('app/new-booking')->where('canChooseOperator', true)->has('services.0.operators', 2)
        ->where('services.0.questions.0.label', 'Reason for visit'));
    $this->postJson(route('portal.service.book', [$portal, $service]), [
        ...manualBookingData(), 'privacy' => true, 'operator' => $second->id, 'answers' => [$question->id => 'First visit'],
    ])->assertUnprocessable()->assertJsonValidationErrors('slot');

    $this->getJson(route('app.bookings.slots', [$service, 'operator' => $second->id, 'day' => '2026-11-19']))
        ->assertOk()->assertJsonPath('slots.0.time', '10:00')->assertJsonPath('slots.0.value', manualBookingData()['slot']);
    $this->post(route('app.bookings.store', $service), [...manualBookingData(), 'operator' => $second->id])
        ->assertSessionHasErrors('answers.'.$question->id);
    $response = $this->post(route('app.bookings.store', $service), [
        ...manualBookingData(), 'operator' => $second->id, 'answers' => [$question->id => 'First visit'], 'privacy' => true,
    ])->assertSessionHasNoErrors();

    $booking = Booking::query()->sole();
    $response->assertRedirect(route('app.bookings', ['booking' => $booking->uid]));
    expect($booking->user_id)->toBe($second->id)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->answers()->sole()->value)->toBe('First visit')
        ->and($booking->privacy_accepted_at)->toBeNull();
    $this->assertDatabaseHas('activity_logs', ['action' => 'booking.created', 'subject_id' => $booking->id, 'user_id' => $manager->id]);
    expect(ActivityLog::query()->where('action', 'booking.created')->sole()->context)->toHaveKey('source', 'agenda');
    Mail::assertQueued(BookingConfirmedMail::class, fn (BookingConfirmedMail $mail) => $mail->hasTo('mario@example.test'));
});

test('an operator sees their own services and can only assign an individual booking to themselves', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-19 08:50 UTC'));
    Mail::fake();
    [$service, , $first, $second] = manualBookingSetup(['location_type' => LocationType::Video]);
    $otherService = Service::factory()->for($service->tenant)->create();
    $otherService->operators()->attach($second);
    $url = route('app.bookings.store', $service);

    $this->actingAs($first)->get(route('app.bookings.create'))->assertInertia(fn (Assert $page) => $page
        ->where('canChooseOperator', false)->has('services', 1)->has('services.0.operators', 1)
        ->where('services.0.operators.0.name', 'Anna'));
    $this->post($url, [...manualBookingData(), 'operator' => $second->id])->assertSessionHasErrors('operator');
    $this->post(route('app.bookings.store', $otherService), manualBookingData())->assertSessionHasErrors('operator');
    $this->post($url, manualBookingData())->assertSessionHasNoErrors();

    $booking = Booking::query()->sole();
    expect($booking->user_id)->toBe($first->id)->and($booking->status)->toBe(BookingStatus::Pending);
    $this->post(route('app.bookings.confirm', $booking))->assertSessionHasErrors('meeting_url');
    Mail::assertQueued(BookingPendingMail::class, fn (BookingPendingMail $mail) => $mail->hasTo('mario@example.test'));
});

test('a previously available slot is rejected when another booking takes its physical resource', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-19 08:50 UTC'));
    Mail::fake();
    [$service, $manager, $first, $second] = manualBookingSetup();
    $resource = Resource::factory()->for($service->tenant)->create();
    $requirement = ServiceResourceRequirement::factory()->for($service)->create(['tenant_id' => $service->tenant_id]);
    $requirement->resources()->attach($resource);
    $url = route('app.bookings.store', $service);

    $this->actingAs($manager)->getJson(route('app.bookings.slots', [$service, 'operator' => $second->id, 'day' => '2026-11-19']))
        ->assertOk()->assertJsonPath('slots.0.time', '10:00');
    $this->post($url, [...manualBookingData(), 'operator' => $first->id])->assertSessionHasNoErrors();
    $this->post($url, [...manualBookingData(), 'operator' => $second->id, 'email' => 'second@example.test'])->assertSessionHasErrors('slot');

    $this->getJson(route('app.bookings.slots', [$service, 'operator' => $second->id, 'day' => '2026-11-19']))
        ->assertOk()->assertJsonPath('slots.0.time', '10:30');
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseCount('resource_allocations', 1);
});

test('staff can enroll customers in their own group session while seats remain', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-19 08:50 UTC'));
    Mail::fake();
    [$service, $manager, $first, $second] = manualBookingSetup(['capacity' => 2]);
    $url = route('app.bookings.store', $service);

    $this->actingAs($manager)->post($url, [...manualBookingData(), 'operator' => $first->id])->assertSessionHasNoErrors();
    $session = $service->sessions()->sole();
    $outsider = User::factory()->operator($service->tenant)->create();
    $service->operators()->attach($outsider);
    $this->actingAs($outsider)->post($url, [...manualBookingData(), 'email' => 'foreign-staff@example.test'])->assertSessionHasErrors('slot');
    $this->actingAs($second)->getJson(route('app.bookings.slots', [$service, 'day' => '2026-11-19']))
        ->assertOk()->assertJsonPath('slots.0.time', '10:00')->assertJsonPath('slots.0.seats_left', 1);
    $response = $this->post($url, [...manualBookingData(), 'email' => 'another@example.test'])->assertSessionHasNoErrors();
    $booking = $session->bookings()->where('customer_email', 'another@example.test')->sole();
    expect($booking->user_id)->toBe($second->id);
    $response->assertRedirect(route('app.bookings', ['booking' => $booking->uid]));
    $this->post($url, [...manualBookingData(), 'email' => 'overflow@example.test'])->assertSessionHasErrors('slot');

    expect($session->seatsTaken())->toBe(2);
    $this->assertDatabaseCount('bookings', 2);
    $this->assertDatabaseCount('service_sessions', 1);
});

test('a manual booking cannot use a foreign service or operator', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-19 08:50 UTC'));
    [$service, $manager] = manualBookingSetup();
    $foreignOperator = User::factory()->operator()->create();
    $foreignService = Service::factory()->for($foreignOperator->tenant)->create();

    $this->actingAs($manager)->getJson(route('app.bookings.slots', [$foreignService, 'day' => '2026-11-19']))
        ->assertNotFound();
    $this->post(route('app.bookings.store', $foreignService), manualBookingData())->assertNotFound();
    $this->post(route('app.bookings.store', $service), [...manualBookingData(), 'operator' => $foreignOperator->id])
        ->assertSessionHasErrors('operator');
    $this->assertDatabaseCount('bookings', 0);
});

test('the manual booking form and submission are restricted to portal staff', function () {
    [$service] = manualBookingSetup();
    $url = route('app.bookings.store', $service);

    $this->get(route('app.bookings.create'))->assertRedirect(route('login'));
    $this->post($url, manualBookingData())->assertRedirect(route('login'));
    $this->actingAs(User::factory()->admin()->create())->get(route('app.bookings.create'))->assertForbidden();
    $this->post($url, manualBookingData())->assertNotFound();

    $this->assertDatabaseCount('bookings', 0);
});
