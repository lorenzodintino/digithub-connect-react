<?php

use App\Actions\Bookings\CreateBooking;
use App\Mail\Customer;
use App\Mail\Staff;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
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

    Livewire::actingAs($this->manager)
        ->test('smtp-settings', ['tenant' => $this->tenant->fresh()])
        ->set('test_recipient', 'marco@studiorossi.test')
        ->call('sendTest');

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

test('the manager saves what is theirs and leaves the server alone', function () {
    // Even driving the properties straight, which is what a crafted request
    // does: the server fields are simply not part of what a manager saves.
    Livewire::actingAs($this->manager)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->set('mail_host', 'smtp.dirottato.test')
        ->set('mail_username', 'ladro@dirottato.test')
        ->set('mail_password', 'segretissima')
        ->set('notify_manager_on_booking', true)
        ->set('reminder_hours_before', '12')
        ->call('save')
        ->assertHasNoErrors();

    $tenant = $this->tenant->fresh();

    expect($tenant->mail_host)->toBeNull()
        ->and($tenant->mail_password)->toBeNull()
        ->and($tenant->hasCustomMailer())->toBeFalse()
        ->and($tenant->notify_manager_on_booking)->toBeTrue()
        ->and($tenant->reminder_hours_before)->toBe(12);
});

test('the server fields are not even drawn for a manager', function () {
    $this->tenant->update(smtpSettings());

    $component = Livewire::actingAs($this->manager)
        ->test('smtp-settings', ['tenant' => $this->tenant->fresh()]);

    // The state is theirs to read — they are the ones who notice the emails
    // stopped — but the credentials are not theirs to hold.
    $component->assertSee('smtp.studiorossi.test')
        ->assertDontSee(__('Encryption'))
        ->assertDontSee('segretissima');
});

test('pasted whitespace is trimmed away from the settings', function () {
    Livewire::actingAs($this->admin)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->set('mail_host', '  authsmtp.example.test ')
        ->set('mail_username', ' noreply@example.test  ')
        ->set('mail_password', ' segretissima ')
        ->set('mail_from_address', '  info@example.test ')
        ->call('save')
        ->assertHasNoErrors();

    $tenant = $this->tenant->fresh();

    // A stray space survives into the DNS lookup and fails as "unknown host".
    expect($tenant->mail_host)->toBe('authsmtp.example.test')
        ->and($tenant->mail_username)->toBe('noreply@example.test')
        ->and($tenant->mail_password)->toBe('segretissima')
        ->and($tenant->mail_from_address)->toBe('info@example.test');
});

test('the preview shows the headers the customer will actually see', function () {
    $component = Livewire::actingAs($this->admin)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->set('mail_host', 'authsmtp.example.test')
        ->set('mail_username', 'noreply@example.test')
        ->set('mail_from_address', '')
        ->set('reply_to_email', '');

    // No sender address: the username stands in. No reply address: the public
    // contact one does.
    expect($component->instance()->preview)->toBe([
        'from' => 'noreply@example.test',
        'replyTo' => 'info@studiorossi.test',
    ]);

    $component
        ->set('mail_from_address', 'prenotazioni@example.test')
        ->set('reply_to_email', 'risposte@example.test');

    expect($component->instance()->preview)->toBe([
        'from' => 'prenotazioni@example.test',
        'replyTo' => 'risposte@example.test',
    ]);
});

test('the preview falls back to the platform sender without SMTP', function () {
    $component = Livewire::actingAs($this->manager)
        ->test('smtp-settings', ['tenant' => $this->tenant]);

    expect($component->instance()->preview['from'])->toBe(config('mail.from.address'));
});

test('a sender on another domain raises a warning but is still allowed', function () {
    $component = Livewire::actingAs($this->admin)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->set('mail_host', 'authsmtp.example.test')
        ->set('mail_username', 'lorenzo@digithub.test')
        ->set('mail_from_address', 'lorenzo.dintino@itlab-group.test');

    expect($component->instance()->senderLooksForeign)->toBeTrue();

    $component->assertSee('The sender belongs to another domain');

    // A warning, not a rule: some accounts really are allowed to send as an alias.
    $component->set('mail_password', 'segretissima')->call('save')->assertHasNoErrors();

    expect($this->tenant->fresh()->mail_from_address)->toBe('lorenzo.dintino@itlab-group.test');
});

test('a sender on the same domain raises nothing', function () {
    $component = Livewire::actingAs($this->manager)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->set('mail_username', 'lorenzo@digithub.test')
        ->set('mail_from_address', 'prenotazioni@digithub.test');

    expect($component->instance()->senderLooksForeign)->toBeFalse();

    $component->assertDontSee('The sender belongs to another domain');
});

test('an empty sender address never warns', function () {
    $component = Livewire::actingAs($this->manager)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->set('mail_username', 'lorenzo@digithub.test')
        ->set('mail_from_address', '');

    expect($component->instance()->senderLooksForeign)->toBeFalse();
});

test('the reply address is saved from the email delivery box', function () {
    Livewire::actingAs($this->manager)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->set('reply_to_email', 'risposte@studiorossi.test')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->tenant->fresh()->notificationReplyTo())->toBe('risposte@studiorossi.test');
});

test('an empty password field keeps the stored one', function () {
    $this->tenant->update(smtpSettings());

    Livewire::actingAs($this->admin)
        ->test('smtp-settings', ['tenant' => $this->tenant->fresh()])
        ->assertSet('mail_password', '')
        ->set('mail_username', 'altro@studiorossi.test')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->tenant->fresh()->mail_password)->toBe('segretissima');
});

test('clearing the server clears the stored password too', function () {
    $this->tenant->update(smtpSettings());

    Livewire::actingAs($this->admin)
        ->test('smtp-settings', ['tenant' => $this->tenant->fresh()])
        ->set('mail_host', '')
        ->call('save');

    $tenant = $this->tenant->fresh();

    expect($tenant->mail_password)->toBeNull()
        ->and($tenant->hasCustomMailer())->toBeFalse();
});

test('the password is never rendered back into the page', function () {
    $this->tenant->update(smtpSettings());

    Livewire::actingAs($this->admin)
        ->test('smtp-settings', ['tenant' => $this->tenant->fresh()])
        ->assertDontSee('segretissima');
});

test('the platform administrator configures any portal', function () {
    Livewire::actingAs($this->admin)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->set('mail_host', 'smtp.studiorossi.test')
        ->set('mail_username', 'noreply@studiorossi.test')
        ->set('mail_password', 'segretissima')
        ->call('save')
        ->assertHasNoErrors();

    $tenant = $this->tenant->fresh();

    // Saved, and not in service until a test message goes through it.
    expect($tenant->hasMailerSettings())->toBeTrue()
        ->and($tenant->hasCustomMailer())->toBeFalse();
});

test('changed settings lose the standing the old ones had', function () {
    $this->tenant->update(smtpSettings());

    Livewire::actingAs($this->admin)
        ->test('smtp-settings', ['tenant' => $this->tenant->fresh()])
        ->set('mail_host', 'altro.studiorossi.test')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->tenant->fresh()->hasCustomMailer())->toBeFalse();
});

test('an operator cannot reach the settings of their own portal', function () {
    Livewire::actingAs($this->operator)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->assertForbidden();
});

test('a manager cannot reach the settings of another portal', function () {
    $other = Tenant::factory()->create();

    Livewire::actingAs($this->manager)
        ->test('smtp-settings', ['tenant' => $other])
        ->assertForbidden();
});

test('a second attempt replaces the previous message instead of keeping it', function () {
    $component = Livewire::actingAs($this->manager)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->set('test_recipient', 'marco@studiorossi.test')
        ->call('sendTest')
        ->assertHasErrors('mail_host');

    expect($component->errors()->first('mail_host'))->toContain('Fill in server');

    // Settings completed, pointing at a port that refuses immediately: the
    // attempt still fails, but with the transport error rather than the old
    // "fill in the fields" complaint left over from before.
    $this->tenant->update(smtpSettings(['mail_host' => '127.0.0.1', 'mail_port' => 1]));

    $component->call('sendTest');

    expect($component->errors()->first('mail_host'))->not->toContain('Fill in server');
});

test('a test email cannot be sent before the settings are complete', function () {
    Livewire::actingAs($this->manager)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->set('test_recipient', 'marco@studiorossi.test')
        ->call('sendTest')
        ->assertHasErrors('mail_host');

    Mail::assertNothingSent();
});

test('the email address in the server field is refused with an explanation', function () {
    // The mistake that costs an afternoon: the transport reports it as a host
    // that does not resolve, which reads like a network fault.
    $component = Livewire::actingAs($this->admin)
        ->test('smtp-settings', ['tenant' => $this->tenant])
        ->set('mail_host', 'mario@example.test')
        ->set('mail_username', 'mario@example.test')
        ->set('mail_password', 'segretissima')
        ->call('save')
        ->assertHasErrors('mail_host');

    expect($component->errors()->first('mail_host'))->toContain('smtp.example.com');

    expect($this->tenant->fresh()->mail_host)->not->toBe('mario@example.test');
});

test('a server field is refused with a scheme, a port or a space in it', function () {
    $component = Livewire::actingAs($this->admin)->test('smtp-settings', ['tenant' => $this->tenant]);

    foreach (['https://smtp.example.test', 'smtp.example.test:465', 'smtp example test', 'non_un_host!'] as $wrong) {
        $component->set('mail_host', $wrong)->call('save')->assertHasErrors('mail_host');
    }

    // A plain host name, an address and a bare machine on the network all pass.
    foreach (['smtp.example.test', '10.0.0.5', 'localhost'] as $fine) {
        $component->set('mail_host', $fine)->call('save')->assertHasNoErrors('mail_host');
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

    Livewire::actingAs($this->manager)
        ->test('smtp-settings', ['tenant' => $this->tenant->fresh()])
        ->assertSee(__('An email did not go out'))
        ->assertSee('Connection refused')
        ->assertDontSee(__('The last test failed'));
});

test('saving the settings clears a failure nobody has looked at yet', function () {
    $this->tenant->update(smtpSettings([
        'mail_verified_at' => null,
        'mail_error' => 'Connection refused',
        'mail_failed_at' => now(),
    ]));

    Livewire::actingAs($this->admin)
        ->test('smtp-settings', ['tenant' => $this->tenant->fresh()])
        ->set('mail_host', 'authsmtp.studiorossi.test')
        ->call('save')
        ->assertHasNoErrors();

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
