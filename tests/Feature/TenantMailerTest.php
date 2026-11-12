<?php

use App\Actions\Bookings\CreateBooking;
use App\Actions\Tenants\TestMailer;
use App\Mail\Customer;
use App\Mail\MailerTestMail;
use App\Mail\Staff;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use App\Support\MailFailure;
use App\Support\MailSettingsData;
use Carbon\CarbonImmutable;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    Mail::fake();

    $this->tenant = Tenant::factory()->create([
        'name' => 'Studio Rossi',
        'timezone' => 'Europe/Rome',
        'contact_email' => 'info@studiorossi.test',
    ]);

    $this->manager = User::factory()->manager($this->tenant)->create(['email' => 'marco@studiorossi.test']);
    $this->admin = User::factory()->admin()->create();

    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'requires_confirmation' => false,
    ]);

    $this->operator = User::factory()->operator($this->tenant)->create(['email' => 'giulia@studiorossi.test']);
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
 * A working SMTP configuration for the portal.
 *
 * @return array<string, mixed>
 */
function smtpSettings(array $overrides = []): array
{
    return [
        'mail_host' => 'smtp.studiorossi.test',
        'mail_port' => 587,
        'mail_username' => 'noreply@studiorossi.test',
        'mail_password' => 'segretissima',
        'mail_encryption' => 'tls',
        'mail_verified_at' => now(),
        ...$overrides,
    ];
}

/**
 * The email delivery form as the page sends it to this reader: the stored
 * values with the password left empty, and the changes typed on top.
 *
 * @return array<string, mixed>
 */
function mailForm(Tenant $tenant, User $reader, array $changes = []): array
{
    return [...MailSettingsData::for($tenant->fresh(), $reader)['form'], ...$changes];
}

/**
 * Place a booking through the real action.
 */
function bookThrough(Service $service, User $operator): Booking
{
    $tomorrow = CarbonImmutable::now($service->tenant->timezone)->addDay()->startOfDay();
    $slot = (new SlotFinder($service, $operator))->slotsForDay($tomorrow)[0];

    return app(CreateBooking::class)->handle($service, $operator, $slot, [
        'name' => 'Mario Bianchi',
        'email' => 'mario@example.com',
        'timezone' => 'Europe/Rome',
        'locale' => 'it',
    ]);
}

test('a portal without SMTP is not considered self hosted', function () {
    expect($this->tenant->hasCustomMailer())->toBeFalse()
        ->and($this->tenant->mailFromAddress())->toBe(config('mail.from.address'));
});

test('host, username and password together enable the portal mailer', function () {
    $this->tenant->update(smtpSettings());

    expect($this->tenant->hasMailerSettings())->toBeTrue()
        ->and($this->tenant->hasCustomMailer())->toBeTrue()
        ->and($this->tenant->mailerConfig())->toMatchArray([
            'transport' => 'smtp',
            'host' => 'smtp.studiorossi.test',
            'port' => 587,
            'username' => 'noreply@studiorossi.test',
            'password' => 'segretissima',
            'encryption' => 'tls',
        ]);
});

test('settings nobody has proved yet keep the platform mailer', function () {
    $this->tenant->update(smtpSettings(['mail_verified_at' => null]));

    expect($this->tenant->hasMailerSettings())->toBeTrue()
        ->and($this->tenant->hasCustomMailer())->toBeFalse()
        ->and($this->tenant->mailFromAddress())->toBe(config('mail.from.address'));

    bookThrough($this->service->fresh(), $this->operator);

    Mail::assertQueued(
        Customer\BookingConfirmedMail::class,
        fn ($mail) => $mail->envelope()->from->address === config('mail.from.address'),
    );
});

test('a test that goes through puts the server into service', function () {
    $this->tenant->update(smtpSettings(['mail_verified_at' => null]));

    $this->actingAs($this->manager)
        ->post(route('app.portal.mail.test'), ['test_recipient' => 'marco@studiorossi.test'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Test email sent to marco@studiorossi.test.');

    $tenant = $this->tenant->fresh();

    expect($tenant->hasCustomMailer())->toBeTrue()
        ->and($tenant->mail_verified_at)->not->toBeNull();
});

test('a delivery that fails stands the portal back down to the platform', function () {
    $this->tenant->update(smtpSettings());

    $booking = Booking::factory()
        ->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->addDay()->setTime(9, 0))
        ->create();

    (new Customer\BookingConfirmedMail($booking))->failed(new TransportException('Connection refused'));

    $tenant = $this->tenant->fresh();

    // The From address is worth less than the message: bookings keep being
    // confirmed, from our address, until the server proves itself again.
    expect($tenant->hasCustomMailer())->toBeFalse()
        ->and($tenant->mailFromAddress())->toBe(config('mail.from.address'))
        ->and($tenant->mailDeliveryFailing())->toBeTrue();
});

test('a partial configuration keeps the platform mailer', function () {
    $this->tenant->update(smtpSettings(['mail_password' => null]));

    expect($this->tenant->hasCustomMailer())->toBeFalse();
});

test('the SMTP password is encrypted at rest', function () {
    $this->tenant->update(smtpSettings());

    $stored = DB::table('tenants')->where('id', $this->tenant->id)->value('mail_password');

    expect($stored)->not->toBe('segretissima')
        ->and($this->tenant->fresh()->mail_password)->toBe('segretissima');
});

test('a portal with its own SMTP sends from its own address', function () {
    $this->tenant->update(smtpSettings(['mail_from_address' => 'prenotazioni@studiorossi.test']));

    bookThrough($this->service->fresh(), $this->operator);

    Mail::assertQueued(Customer\BookingConfirmedMail::class, function ($mail) {
        $envelope = $mail->envelope();

        return $envelope->from->address === 'prenotazioni@studiorossi.test'
            && $envelope->from->name === 'Studio Rossi';
    });
});

test('without a sender address the SMTP username is used', function () {
    $this->tenant->update(smtpSettings());

    expect($this->tenant->mailFromAddress())->toBe('noreply@studiorossi.test');
});

test('the test message is sent as the portal, not as the platform', function () {
    // The settings have not been proved yet — that is the whole point of the
    // test message — but it still leaves through the portal's own server, so
    // the platform address in the From would be a foreign sender there.
    $this->tenant->update(smtpSettings([
        'mail_verified_at' => null,
        'mail_from_address' => 'prenotazioni@studiorossi.test',
    ]));

    $envelope = (new MailerTestMail($this->tenant->fresh()))->envelope();

    expect($envelope->from->address)->toBe('prenotazioni@studiorossi.test')
        ->and($envelope->from->name)->toBe('Studio Rossi');
});

test('without a sender address the test message goes out as the SMTP account', function () {
    $this->tenant->update(smtpSettings(['mail_verified_at' => null, 'mail_from_address' => null]));

    expect((new MailerTestMail($this->tenant->fresh()))->envelope()->from->address)
        ->toBe('noreply@studiorossi.test');
});

test('the mailer name is unique per portal', function () {
    $other = Tenant::factory()->create();

    expect($this->tenant->mailerName())->toBe('tenant.'.$this->tenant->id)
        ->and($this->tenant->mailerName())->not->toBe($other->mailerName());
});

test('sending registers the portal mailer and selects it', function () {
    $this->tenant->update(smtpSettings());

    $booking = bookThrough($this->service->fresh(), $this->operator);
    $mail = new Customer\BookingConfirmedMail($booking->fresh());

    $mail->send(app('mail.manager'));

    expect(config('mail.mailers.'.$this->tenant->mailerName().'.host'))->toBe('smtp.studiorossi.test');
});

test('the manager gets a copy only when the portal asked for it', function () {
    bookThrough($this->service, $this->operator);

    Mail::assertQueued(
        Staff\BookingPlacedMail::class,
        fn ($mail) => ! $mail->hasTo('marco@studiorossi.test'),
    );

    Mail::fake();
    $this->tenant->update(['notify_manager_on_booking' => true]);

    bookThrough($this->service->fresh(), $this->operator);

    Mail::assertQueued(Staff\BookingPlacedMail::class, fn ($mail) => $mail->hasTo('marco@studiorossi.test'));
});

test('a bookable manager is never copied on their own booking', function () {
    $this->tenant->update(['notify_manager_on_booking' => true]);
    $this->manager->update(['is_bookable' => true]);
    $this->service->operators()->attach($this->manager);

    foreach (range(0, 6) as $weekday) {
        $this->manager->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
        ]);
    }

    bookThrough($this->service->fresh(), $this->manager->fresh());

    // One to the customer, one to the manager as the operator. Not two.
    Mail::assertQueuedCount(2);
});

test('the manager saves what is theirs and leaves a working server in service', function () {
    // The server fields travel with the form the page sends, and a crafted
    // request can change them: none of it is the manager's to save, and saving
    // the rest must not take a proved server out of service either.
    $this->tenant->update(smtpSettings());

    $this->actingAs($this->manager)
        ->put(route('app.portal.mail.update'), mailForm($this->tenant, $this->manager, [
            'mail_host' => 'smtp.dirottato.test',
            'mail_username' => 'ladro@dirottato.test',
            'mail_password' => 'altra',
            'notify_manager_on_booking' => true,
            'reminder_hours_before' => '12',
        ]))
        ->assertSessionHasNoErrors();

    $tenant = $this->tenant->fresh();

    expect($tenant->mail_host)->toBe('smtp.studiorossi.test')
        ->and($tenant->mail_password)->toBe('segretissima')
        ->and($tenant->hasCustomMailer())->toBeTrue()
        ->and($tenant->notify_manager_on_booking)->toBeTrue()
        ->and($tenant->reminder_hours_before)->toBe(12);
});

test('the manager saves what is theirs from the portal settings and leaves the server alone', function () {
    // The React portal settings send whatever a crafted request may add.
    $this->actingAs($this->manager)
        ->put(route('app.portal.mail.update'), [
            'mail_host' => 'smtp.dirottato.test',
            'mail_username' => 'ladro@dirottato.test',
            'mail_password' => 'segretissima',
            'reply_to_email' => ' segreteria@studiorossi.test ',
            'notify_manager_on_booking' => true,
            'reminder_hours_before' => '12',
        ])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Email settings saved.');

    $tenant = $this->tenant->fresh();

    expect($tenant->mail_host)->toBeNull()
        ->and($tenant->mail_password)->toBeNull()
        ->and($tenant->reply_to_email)->toBe('segreteria@studiorossi.test')
        ->and($tenant->notify_manager_on_booking)->toBeTrue()
        ->and($tenant->reminder_hours_before)->toBe(12);
});

test('the portal settings show the email state to the manager, never the password', function () {
    $this->tenant->update(smtpSettings(['mail_verified_at' => null]));

    $this->actingAs($this->manager)
        ->get(route('app.portal'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mail.canConfigure', false)
            ->where('mail.state', 'configured')
            ->where('mail.form.mail_host', 'smtp.studiorossi.test')
            ->where('mail.form.mail_password', ''));
});

test('the manager test email asks for a configured server first', function () {
    $this->actingAs($this->manager)
        ->post(route('app.portal.mail.test'), ['test_recipient' => 'marco@studiorossi.test'])
        ->assertSessionHasErrors(['mail_host' => 'Fill in server, username and password first, then save.']);

    Mail::assertNothingSent();
});

test('the server fields are not even drawn for a manager', function () {
    $this->tenant->update(smtpSettings());

    // The state is theirs to read — they are the ones who notice the emails
    // stopped — but the credentials are not theirs to hold.
    $this->actingAs($this->manager)
        ->get(route('app.portal'))
        ->assertDontSee('segretissima')
        ->assertInertia(fn (Assert $page) => $page
            ->where('mail.canConfigure', false)
            ->where('mail.form.mail_host', 'smtp.studiorossi.test'));
});

test('pasted whitespace is trimmed away from the settings', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.portals.mail.update', $this->tenant), mailForm($this->tenant, $this->admin, [
            'mail_host' => '  authsmtp.example.test ',
            'mail_username' => ' noreply@example.test  ',
            'mail_password' => ' segretissima ',
            'mail_from_address' => '  info@example.test ',
        ]))
        ->assertSessionHasNoErrors();

    $tenant = $this->tenant->fresh();

    // A stray space survives into the DNS lookup and fails as "unknown host".
    expect($tenant->mail_host)->toBe('authsmtp.example.test')
        ->and($tenant->mail_username)->toBe('noreply@example.test')
        ->and($tenant->mail_password)->toBe('segretissima')
        ->and($tenant->mail_from_address)->toBe('info@example.test');
});

test('the preview gets what it needs to show the headers the customer will see', function () {
    // The page works out From and Reply-To as the fields change; the server
    // hands it the sender in force and the fallbacks.
    $this->tenant->update(smtpSettings());

    $this->actingAs($this->admin)
        ->get(route('admin.portals.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mail.usesOwnMailer', true)
            ->where('mail.sendingAs', 'noreply@studiorossi.test')
            ->where('mail.contactEmail', 'info@studiorossi.test')
            ->where('mail.platformFrom', config('mail.from.address')));
});

test('an unproved server does not get to claim the sender yet', function () {
    // The settings are there but nothing sends through them: promising the
    // portal's own address would contradict the notice right above it.
    $this->tenant->update(smtpSettings(['mail_verified_at' => null, 'mail_from_address' => 'prenotazioni@studiorossi.test']));

    $this->actingAs($this->admin)
        ->get(route('admin.portals.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mail.state', 'configured')
            ->where('mail.usesOwnMailer', false)
            ->where('mail.sendingAs', null)
            ->where('mail.form.mail_from_address', 'prenotazioni@studiorossi.test'));
});

test('the preview falls back to the platform sender without SMTP', function () {
    $this->actingAs($this->manager)
        ->get(route('app.portal'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mail.state', 'platform')
            ->where('mail.usesOwnMailer', false)
            ->where('mail.platformFrom', config('mail.from.address')));
});

test('a sender on another domain is still allowed', function () {
    // The page warns about it, but it is a warning, not a rule: some accounts
    // really are allowed to send as an alias.
    $this->actingAs($this->admin)
        ->put(route('admin.portals.mail.update', $this->tenant), mailForm($this->tenant, $this->admin, [
            'mail_host' => 'authsmtp.example.test',
            'mail_username' => 'lorenzo@digithub.test',
            'mail_password' => 'segretissima',
            'mail_from_address' => 'lorenzo.dintino@itlab-group.test',
        ]))
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->mail_from_address)->toBe('lorenzo.dintino@itlab-group.test');
});

test('the reply address is saved from the email delivery box', function () {
    $this->actingAs($this->manager)
        ->put(route('app.portal.mail.update'), mailForm($this->tenant, $this->manager, ['reply_to_email' => 'risposte@studiorossi.test']))
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->notificationReplyTo())->toBe('risposte@studiorossi.test');
});

test('an empty password field keeps the stored one', function () {
    $this->tenant->update(smtpSettings());

    $form = mailForm($this->tenant, $this->admin, ['mail_username' => 'altro@studiorossi.test']);

    expect($form['mail_password'])->toBe('');

    $this->actingAs($this->admin)
        ->put(route('admin.portals.mail.update', $this->tenant), $form)
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->mail_password)->toBe('segretissima');
});

test('clearing the server clears the stored password too', function () {
    $this->tenant->update(smtpSettings());

    $this->actingAs($this->admin)
        ->put(route('admin.portals.mail.update', $this->tenant), mailForm($this->tenant, $this->admin, ['mail_host' => '']))
        ->assertSessionHasNoErrors();

    $tenant = $this->tenant->fresh();

    expect($tenant->mail_password)->toBeNull()
        ->and($tenant->hasCustomMailer())->toBeFalse();
});

test('the password is never rendered back into the page', function () {
    $this->tenant->update(smtpSettings());

    $this->actingAs($this->admin)
        ->get(route('admin.portals.show', $this->tenant))
        ->assertDontSee('segretissima')
        ->assertInertia(fn (Assert $page) => $page
            ->where('mail.canConfigure', true)
            ->where('mail.form.mail_password', ''));
});

test('the platform administrator configures any portal', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.portals.mail.update', $this->tenant), mailForm($this->tenant, $this->admin, [
            'mail_host' => 'smtp.studiorossi.test',
            'mail_username' => 'noreply@studiorossi.test',
            'mail_password' => 'segretissima',
        ]))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.message', 'Email settings saved.');

    $tenant = $this->tenant->fresh();

    // Saved, and not in service until a test message goes through it.
    expect($tenant->hasMailerSettings())->toBeTrue()
        ->and($tenant->hasCustomMailer())->toBeFalse();
});

test('changed settings lose the standing the old ones had', function () {
    $this->tenant->update(smtpSettings());

    $this->actingAs($this->admin)
        ->put(route('admin.portals.mail.update', $this->tenant), mailForm($this->tenant, $this->admin, ['mail_host' => 'altro.studiorossi.test']))
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->hasCustomMailer())->toBeFalse();
});

test('an operator cannot reach the settings of their own portal', function () {
    $this->actingAs($this->operator)
        ->put(route('app.portal.mail.update'), ['reply_to_email' => 'furbo@example.test'])
        ->assertForbidden();

    $this->put(route('admin.portals.mail.update', $this->tenant), ['reply_to_email' => 'furbo@example.test'])->assertForbidden();

    expect($this->tenant->fresh()->reply_to_email)->toBeNull();
});

test('a manager cannot reach the settings of another portal', function () {
    $other = Tenant::factory()->create();

    $this->actingAs($this->manager)
        ->put(route('admin.portals.mail.update', $other), ['reply_to_email' => 'furbo@example.test'])
        ->assertForbidden();

    $this->post(route('admin.portals.mail.test', $other), ['test_recipient' => 'furbo@example.test'])->assertForbidden();

    expect($other->fresh()->reply_to_email)->toBeNull();
});

test('a second attempt replaces the previous message instead of keeping it', function () {
    // What the screen shows after each attempt: the redirect back reads the errors.
    $this->actingAs($this->manager)
        ->from(route('app.portal'))
        ->followingRedirects()
        ->post(route('app.portal.mail.test'), ['test_recipient' => 'marco@studiorossi.test'])
        ->assertInertia(fn (Assert $page) => $page->where('errors.mail_host', fn (string $message) => str_contains($message, 'Fill in server')));

    // Settings completed, pointing at a port that refuses immediately: the
    // attempt still fails, but with the transport error rather than the old
    // "fill in the fields" complaint left over from before. Through the
    // manager: the signed-in user is the same object across test requests and
    // keeps the portal it loaded the first time.
    $this->manager->tenant->update(smtpSettings(['mail_host' => '127.0.0.1', 'mail_port' => 1]));

    $this->from(route('app.portal'))
        ->followingRedirects()
        ->post(route('app.portal.mail.test'), ['test_recipient' => 'marco@studiorossi.test'])
        ->assertInertia(fn (Assert $page) => $page->where('errors', fn ($errors) => ! str_contains((string) collect($errors)->get('mail_host', ''), 'Fill in server')));
});

test('a test email cannot be sent before the settings are complete', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.portals.mail.test', $this->tenant), ['test_recipient' => 'admin@digithub.test'])
        ->assertSessionHasErrors(['mail_host' => 'Fill in server, username and password first, then save.']);

    Mail::assertNothingSent();
});

test('the email address in the server field is refused with an explanation', function () {
    // The mistake that costs an afternoon: the transport reports it as a host
    // that does not resolve, which reads like a network fault.
    $this->actingAs($this->admin)
        ->put(route('admin.portals.mail.update', $this->tenant), mailForm($this->tenant, $this->admin, [
            'mail_host' => 'mario@example.test',
            'mail_username' => 'mario@example.test',
            'mail_password' => 'segretissima',
        ]))
        ->assertSessionHasErrors('mail_host');

    expect(session('errors')->first('mail_host'))->toContain('smtp.example.com')
        ->and($this->tenant->fresh()->mail_host)->not->toBe('mario@example.test');
});

test('a server field is refused with a scheme, a port or a space in it', function () {
    $this->actingAs($this->admin);

    foreach (['https://smtp.example.test', 'smtp.example.test:465', 'smtp example test', 'non_un_host!'] as $wrong) {
        $this->put(route('admin.portals.mail.update', $this->tenant), mailForm($this->tenant, $this->admin, ['mail_host' => $wrong]))
            ->assertSessionHasErrors('mail_host');
    }

    // A plain host name, an address and a bare machine on the network all pass.
    foreach (['smtp.example.test', '10.0.0.5', 'localhost'] as $fine) {
        $this->put(route('admin.portals.mail.update', $this->tenant), mailForm($this->tenant, $this->admin, ['mail_host' => $fine]))
            ->assertSessionHasNoErrors();
    }
});

test('an email that never goes out is written on the portal', function () {
    $this->tenant->update([
        'mail_host' => 'smtp.studiorossi.test',
        'mail_username' => 'noreply@studiorossi.test',
        'mail_password' => 'segretissima',
        'mail_tested_at' => now()->subDay(),
        'mail_verified_at' => now()->subDay(),
        'mail_error' => null,
    ]);

    $booking = Booking::factory()
        ->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->addDay()->setTime(10, 0))
        ->create();

    // What the queue does once the job has given up: the SMTP conversation is
    // long and can echo credentials back, so only its first line is kept.
    (new Customer\BookingConfirmedMail($booking))->failed(new TransportException(
        "Connection could not be established with host \"smtp.studiorossi.test:587\"\nAUTH LOGIN bm9yZXBseQ=="
    ));

    $tenant = $this->tenant->fresh();

    expect($tenant->mailDeliveryFailing())->toBeTrue()
        ->and($tenant->mail_error)->toBe('Connection could not be established with host "smtp.studiorossi.test:587"')
        ->and($tenant->mail_failed_at)->not->toBeNull();
});

test('a failure on the platform mailer is not blamed on the portal', function () {
    // No SMTP of its own: the manager can do nothing about it, and the address
    // that failed is ours to chase.
    $booking = Booking::factory()
        ->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->addDay()->setTime(11, 0))
        ->create();

    (new Customer\BookingConfirmedMail($booking))->failed(new TransportException('Connection refused'));

    expect($this->tenant->fresh()->mail_error)->toBeNull();
});

test('the settings screen tells a dead delivery apart from a failed test', function () {
    $this->tenant->update(smtpSettings([
        'mail_verified_at' => null,
        'mail_tested_at' => now()->subDay(),
        'mail_error' => 'Connection refused',
        'mail_failed_at' => now(),
    ]));

    $this->actingAs($this->manager)
        ->get(route('app.portal'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mail.state', 'failing')
            ->where('mail.error', 'Connection refused'));
});

test('saving the settings clears a failure nobody has looked at yet', function () {
    $this->tenant->update(smtpSettings([
        'mail_verified_at' => null,
        'mail_error' => 'Connection refused',
        'mail_failed_at' => now(),
    ]));

    $this->actingAs($this->admin)
        ->put(route('admin.portals.mail.update', $this->tenant), mailForm($this->tenant, $this->admin, ['mail_host' => 'authsmtp.studiorossi.test']))
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->mailDeliveryFailing())->toBeFalse();
});

test('the queue is what calls that hook once a job gives up', function () {
    $this->tenant->update(smtpSettings());

    $booking = Booking::factory()
        ->forSlot($this->service, $this->operator, CarbonImmutable::now('UTC')->addDay()->setTime(12, 0))
        ->create();

    // The job the framework wraps a queued mailable in. Asserted here because
    // the whole report hangs on it keeping this contract.
    (new SendQueuedMailable(new Customer\BookingConfirmedMail($booking)))
        ->failed(new TransportException('Connection refused'));

    expect($this->tenant->fresh()->mailDeliveryFailing())->toBeTrue();
});

test('a transport error is turned into something the person can act on', function () {
    // The three refusals a real provider actually returns, in the words the
    // Symfony transport uses for them.
    expect(MailFailure::explain('Failed to authenticate on SMTP server with username "ufficio@studio.test" using the following authenticators: "LOGIN", "PLAIN".'))
        ->toContain('app password')
        ->and(MailFailure::explain('Expected response code "250" but got code "550", with message "550 5.7.1 Sender address not allowed".'))
        ->toContain('sender field')
        ->and(MailFailure::explain('Connection could not be established with host "smtp.studiorossi.test:587": stream_socket_client(): Unable to connect'))
        ->toContain('587')
        ->and(MailFailure::explain('Connection could not be established with host "smtp.typo.test:587": php_network_getaddresses: getaddrinfo failed'))
        ->toContain('smtp.example.com');

    // Nothing is invented for an error we do not know: the server's own words
    // are better than a confident guess about the wrong field.
    expect(MailFailure::explain('Something nobody has seen before'))->toBeNull()
        ->and(MailFailure::explain(null))->toBeNull();
});

test('a failed test says what to do, and keeps the server words as evidence', function () {
    $this->tenant->update(smtpSettings());

    // The transport cannot really fail under Mail::fake(), so the refusal comes
    // from the action that owns it — the wiring under test is what the screen
    // does with the error, not how the error is produced.
    $this->swap(TestMailer::class, new class extends TestMailer
    {
        public function handle(Tenant $tenant, string $recipient): array
        {
            $error = 'Failed to authenticate on SMTP server with username "noreply@studiorossi.test" using the following authenticators: "LOGIN", "PLAIN".';

            $tenant->update(['mail_tested_at' => now(), 'mail_error' => $error, 'mail_failed_at' => now(), 'mail_verified_at' => null]);

            return ['sent' => false, 'error' => $error];
        }
    });

    $this->actingAs($this->admin)
        ->post(route('admin.portals.mail.test', $this->tenant), ['test_recipient' => 'admin@digithub.test'])
        ->assertSessionHasErrors('mail_host')
        ->assertInertiaFlash('toast.type', 'error');

    // On the field, the instruction; in the box, what the server actually said.
    expect(session('errors')->first('mail_host'))->toContain('app password')
        ->not->toContain('authenticators');

    $this->get(route('admin.portals.show', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('mail.error', fn (string $error) => str_contains($error, 'using the following authenticators')));
});

test('the actionable explanation is written in the language of the screen', function () {
    // The manager reads this box in Italian, and the transport speaks English.
    $this->manager->update(['locale' => 'it']);

    $this->tenant->update(smtpSettings([
        'mail_verified_at' => null,
        'mail_tested_at' => now()->subDay(),
        'mail_error' => 'Expected response code "250" but got code "550", with message "550 5.7.1 Sender address not allowed".',
        'mail_failed_at' => now(),
    ]));

    $this->actingAs($this->manager)
        ->get(route('app.portal'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mail.errorHint', fn (string $hint) => str_contains($hint, "Il server ha rifiutato l'indirizzo mittente"))
            ->where('mail.error', fn (string $error) => str_contains($error, '550 5.7.1 Sender address not allowed')));
});
