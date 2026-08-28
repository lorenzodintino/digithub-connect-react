<?php

use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\RescheduleBooking;
use App\Mail\Customer;
use App\Mail\Staff\BookingPlacedMail;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use App\Support\IcsEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->tenant = Tenant::factory()->create([
        'name' => 'Studio Rossi',
        'timezone' => 'Europe/Rome',
        'contact_email' => 'info@studiorossi.test',
    ]);

    $this->service = Service::factory()->for($this->tenant)->create([
        'name' => 'Prima consulenza',
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
    ]);

    $this->operator = User::factory()->operator($this->tenant)->create(['name' => 'Giulia Bianchi']);
    $this->service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
        ]);
    }

    $tomorrow = CarbonImmutable::now($this->tenant->timezone)->addDay()->startOfDay();
    $this->slot = (new SlotFinder($this->service, $this->operator))->slotsForDay($tomorrow)[0];

    $this->booking = Booking::factory()->forSlot($this->service, $this->operator, $this->slot)->create();
});

test('the file carries the essentials a calendar needs', function () {
    $ics = (new IcsEvent($this->booking))->toString();

    expect($ics)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('END:VCALENDAR')
        ->toContain('METHOD:REQUEST')
        ->toContain('STATUS:CONFIRMED')
        ->toContain('SEQUENCE:0')
        ->toContain('UID:'.$this->booking->uid.'@')
        ->toContain('DTSTART:'.$this->booking->starts_at->format('Ymd\THis\Z'))
        ->toContain('DTEND:'.$this->booking->ends_at->format('Ymd\THis\Z'))
        ->toContain('Prima consulenza');
});

test('every line ends the way the format demands', function () {
    $ics = (new IcsEvent($this->booking))->toString();

    expect($ics)->toEndWith("\r\n")
        ->and(substr_count($ics, "\n"))->toBe(substr_count($ics, "\r\n"));
});

test('the UID survives a reschedule while the sequence grows', function () {
    $before = new IcsEvent($this->booking);

    expect($before->toString())->toContain('SEQUENCE:0');

    $moved = app(RescheduleBooking::class)->handle($this->booking, $this->slot->addHour());
    $after = (new IcsEvent($moved))->toString();

    // Same UID, higher sequence: the calendar replaces the event rather than
    // leaving two of them behind.
    expect($after)->toContain('UID:'.$moved->uid.'@')
        ->toContain('SEQUENCE:1')
        ->toContain('DTSTART:'.$this->slot->addHour()->format('Ymd\THis\Z'));
});

test('two reschedules do not leave three events behind', function () {
    $moved = app(RescheduleBooking::class)->handle($this->booking, $this->slot->addHour());
    $moved = app(RescheduleBooking::class)->handle($moved, $this->slot->addHours(2));

    expect((new IcsEvent($moved))->toString())->toContain('SEQUENCE:2');
});

test('a cancellation is announced as a cancellation', function () {
    $cancelled = app(CancelBooking::class)->handle($this->booking);
    $ics = (new IcsEvent($cancelled))->toString();

    expect($ics)->toContain('METHOD:CANCEL')
        ->toContain('STATUS:CANCELLED')
        ->toContain('SEQUENCE:1');
});

test('commas and line breaks in the text do not break the file', function () {
    $this->service->update([
        'name' => 'Consulenza, prima visita',
        'description' => "Prima riga;\nseconda riga, con virgola",
    ]);

    $ics = (new IcsEvent($this->booking->fresh()))->toString();

    // Escaped, so the value cannot end early and swallow the rest.
    expect($ics)->toContain('Consulenza\, prima visita')
        ->toContain('Prima riga\;')
        ->not->toContain('seconda riga, con virgola');
});

test('a long line is folded and continues with a leading space', function () {
    $this->service->update([
        'description' => str_repeat('descrizione molto lunga ', 12),
    ]);

    $ics = (new IcsEvent($this->booking->fresh()))->toString();

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }

    expect($ics)->toContain("\r\n ");
});

test('accented characters are never cut in half by the folding', function () {
    $this->service->update(['description' => str_repeat('perché è così à ù ', 10)]);

    $ics = (new IcsEvent($this->booking->fresh()))->toString();

    expect(mb_check_encoding($ics, 'UTF-8'))->toBeTrue();
});

test('the confirmation email carries the file', function () {
    $mail = new Customer\BookingConfirmedMail($this->booking);

    $attachments = $mail->attachments();

    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]->as)->toEndWith('.ics');
});

test('a request still awaiting approval carries nothing', function () {
    $mail = new Customer\BookingPendingMail($this->booking);

    expect($mail->attachments())->toBe([]);
});

test('the operator email carries no calendar file either', function () {
    $mail = new BookingPlacedMail($this->booking);

    expect($mail->attachments())->toBe([]);
});

test('the customer can download the file from the confirmation page', function () {
    $this->get(route('portal.booking.calendar', [$this->tenant, $this->booking]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertSee('BEGIN:VCALENDAR', escape: false);
});

test('the download refuses a booking from another portal', function () {
    $other = Tenant::factory()->create();

    $this->get(route('portal.booking.calendar', [$other->slug, $this->booking->uid]))
        ->assertNotFound();
});

test('the confirmation page offers one button per calendar', function () {
    $this->get(route('portal.booking', [$this->tenant, $this->booking]))
        ->assertOk()
        ->assertSee('calendar.google.com', escape: false)
        ->assertSee('outlook.live.com', escape: false)
        ->assertSee('Apple Calendar')
        ->assertSee(route('portal.booking.calendar', [$this->tenant, $this->booking, 'inline' => 1]), escape: false)
        ->assertSee(route('portal.booking.calendar', [$this->tenant, $this->booking]), escape: false);
});

test('the Apple button serves the file inline so iOS opens it in the calendar', function () {
    $this->get(route('portal.booking.calendar', [$this->tenant, $this->booking, 'inline' => 1]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertHeader('Content-Disposition', 'inline; filename="'.(new IcsEvent($this->booking))->fileName().'"');

    $this->get(route('portal.booking.calendar', [$this->tenant, $this->booking]))
        ->assertHeader('Content-Disposition', 'attachment; filename="'.(new IcsEvent($this->booking))->fileName().'"');
});
