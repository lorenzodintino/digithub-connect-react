<?php

use App\Actions\Showcase\TestShowcaseMailer;
use App\Mail\DemoRequestMail;
use App\Mail\ShowcaseMailTestMail;
use App\Models\ShowcaseMailSettings;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    Mail::fake();

    $this->admin = User::factory()->admin()->create(['email' => 'admin@digithub.test']);
});

/**
 * The form of the page as the administrator sends it.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function showcaseForm(array $changes = []): array
{
    return array_merge([
        'recipient' => 'demo@atrio.test',
        'mail_host' => 'smtp.example.com',
        'mail_port' => '587',
        'mail_username' => 'vetrina@example.com',
        'mail_password' => 'secret-password',
        'mail_encryption' => 'tls',
        'mail_from_address' => '',
    ], $changes);
}

test('only the administrator opens the page', function () {
    $manager = User::factory()->manager(Tenant::factory()->create())->create();

    $this->get(route('admin.showcase-mail'))->assertRedirect(route('login'));
    $this->actingAs($manager)->get(route('admin.showcase-mail'))->assertForbidden();
    $this->actingAs($manager)->put(route('admin.showcase-mail.update'), showcaseForm())->assertForbidden();
    $this->actingAs($manager)->post(route('admin.showcase-mail.test'), ['test_recipient' => 'a@example.test'])->assertForbidden();

    $this->actingAs($this->admin)->get(route('admin.showcase-mail'))->assertOk();
});

test('with nothing saved the form of the home page is off', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.showcase-mail'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/showcase-mail')
            ->where('showcase.state', 'off')
            ->where('showcase.recipient', null)
            ->where('showcase.form.mail_port', '587'));

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('demoEnabled', false));
});

test('saving the recipient turns the form of the home page on', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.showcase-mail.update'), showcaseForm(['mail_host' => '', 'mail_username' => '', 'mail_password' => '']))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    expect(ShowcaseMailSettings::current()->recipient)->toBe('demo@atrio.test');

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('demoEnabled', true));

    $this->actingAs($this->admin)
        ->get(route('admin.showcase-mail'))
        ->assertInertia(fn (Assert $page) => $page->where('showcase.state', 'platform'));
});

test('the environment address turns the form on until one is saved', function () {
    config(['app.demo_email' => 'ambiente@atrio.test']);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('demoEnabled', true));

    $this->actingAs($this->admin)
        ->get(route('admin.showcase-mail'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('showcase.recipient', 'ambiente@atrio.test')
            ->where('showcase.recipientFromEnvironment', true)
            ->where('showcase.form.recipient', ''));
});

test('the password is stored encrypted and never leaves the server', function () {
    $this->actingAs($this->admin)->put(route('admin.showcase-mail.update'), showcaseForm())->assertSessionHasNoErrors();

    expect(ShowcaseMailSettings::current()->mail_password)->toBe('secret-password')
        ->and(DB::table('showcase_mail_settings')->value('mail_password'))->not->toBe('secret-password');

    $response = $this->actingAs($this->admin)->get(route('admin.showcase-mail'));

    expect($response->getContent())->not->toContain('secret-password');
    $response->assertInertia(fn (Assert $page) => $page
        ->where('showcase.hasMailerSettings', true)
        ->where('showcase.form.mail_password', '')
        ->where('showcase.state', 'configured'));
});

test('an empty password keeps the one stored, and clearing the server clears it', function () {
    $this->actingAs($this->admin)->put(route('admin.showcase-mail.update'), showcaseForm());
    $this->actingAs($this->admin)->put(route('admin.showcase-mail.update'), showcaseForm(['mail_password' => '', 'mail_port' => '2525']));

    expect(ShowcaseMailSettings::current()->mail_password)->toBe('secret-password')
        ->and(ShowcaseMailSettings::current()->mail_port)->toBe(2525);

    $this->actingAs($this->admin)->put(route('admin.showcase-mail.update'), showcaseForm(['mail_host' => '', 'mail_username' => '', 'mail_password' => '']));

    expect(ShowcaseMailSettings::current()->mail_password)->toBeNull()
        ->and(ShowcaseMailSettings::current()->hasMailerSettings())->toBeFalse();
});

test('the server fields are checked', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.showcase-mail.update'), showcaseForm(['mail_host' => 'me@example.com', 'mail_port' => '70000', 'recipient' => 'non-una-email', 'mail_encryption' => 'strano']))
        ->assertSessionHasErrors(['mail_host', 'mail_port', 'recipient', 'mail_encryption']);
});

test('a server is not in service until a test message went through it', function () {
    ShowcaseMailSettings::factory()->withServer()->create();

    expect(ShowcaseMailSettings::current()->hasCustomMailer())->toBeFalse();

    $this->actingAs($this->admin)
        ->post(route('admin.showcase-mail.test'), ['test_recipient' => 'admin@digithub.test'])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    Mail::assertSent(ShowcaseMailTestMail::class, fn (ShowcaseMailTestMail $mail) => $mail->hasTo('admin@digithub.test')
        && $mail->hasFrom('vetrina@example.com'));

    $settings = ShowcaseMailSettings::current();

    expect($settings->hasCustomMailer())->toBeTrue()
        ->and($settings->mail_verified_at)->not->toBeNull()
        ->and($settings->mailFromAddress())->toBe('vetrina@example.com');
});

test('a refused test leaves the server out of service and says what to do', function () {
    ShowcaseMailSettings::factory()->verified()->create();

    // The transport cannot really fail under Mail::fake(), so the refusal comes
    // from the action that owns it: what is under test is what the page does with it.
    $this->swap(TestShowcaseMailer::class, new class extends TestShowcaseMailer
    {
        public function handle(ShowcaseMailSettings $settings, string $recipient): array
        {
            $error = 'Failed to authenticate on SMTP server with username "vetrina@example.com" using the following authenticators: "LOGIN", "PLAIN".';

            $settings->update(['mail_tested_at' => now(), 'mail_error' => $error, 'mail_failed_at' => now(), 'mail_verified_at' => null]);

            return ['sent' => false, 'error' => $error];
        }
    });

    $this->actingAs($this->admin)
        ->post(route('admin.showcase-mail.test'), ['test_recipient' => 'admin@digithub.test'])
        ->assertSessionHasErrors('mail_host')
        ->assertInertiaFlash('toast.type', 'error');

    expect(session('errors')->first('mail_host'))->toContain('app password')
        ->and(ShowcaseMailSettings::current()->hasCustomMailer())->toBeFalse();

    $this->get(route('admin.showcase-mail'))
        ->assertInertia(fn (Assert $page) => $page
            // A refused test is something a person saw on the screen, not a request that never arrived.
            ->where('showcase.state', 'configured')
            ->where('showcase.error', fn (string $error) => str_contains($error, 'using the following authenticators')));
});

test('the test needs a server saved first and a valid address', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.showcase-mail.test'), ['test_recipient' => 'admin@digithub.test'])
        ->assertSessionHasErrors('mail_host');

    ShowcaseMailSettings::factory()->withServer()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.showcase-mail.test'), ['test_recipient' => 'non-una-email'])
        ->assertSessionHasErrors('test_recipient');

    Mail::assertNothingSent();
});

test('saving only the recipient does not put a proved server in doubt', function () {
    ShowcaseMailSettings::factory()->verified()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.showcase-mail.update'), showcaseForm(['recipient' => 'altro@atrio.test', 'mail_password' => '']))
        ->assertSessionHasNoErrors();

    expect(ShowcaseMailSettings::current()->hasCustomMailer())->toBeTrue()
        ->and(ShowcaseMailSettings::current()->recipient)->toBe('altro@atrio.test');
});

test('changing the server puts it in doubt until a new test goes through', function () {
    ShowcaseMailSettings::factory()->verified()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.showcase-mail.update'), showcaseForm(['mail_host' => 'smtp.altro.example', 'mail_password' => '']))
        ->assertSessionHasNoErrors();

    expect(ShowcaseMailSettings::current()->hasCustomMailer())->toBeFalse();

    ShowcaseMailSettings::query()->update(['mail_verified_at' => now()]);

    $this->actingAs($this->admin)
        ->put(route('admin.showcase-mail.update'), showcaseForm(['mail_host' => 'smtp.altro.example', 'mail_password' => 'nuova-password']))
        ->assertSessionHasNoErrors();

    expect(ShowcaseMailSettings::current()->hasCustomMailer())->toBeFalse();
});

test('a request that cannot be delivered stands the server down and shows on the page', function () {
    ShowcaseMailSettings::factory()->verified()->create(['mail_tested_at' => now()->subDay()]);

    $mail = new DemoRequestMail('Anna Ferrari', 'anna@example.test', null, null, null, 'it', '18 nov 2026, 10:30');
    $mail->failed(new TransportException('Connection could not be established with host "smtp.example.com:587"'));

    $settings = ShowcaseMailSettings::current();

    expect($settings->hasCustomMailer())->toBeFalse()
        ->and($settings->mail_error)->toContain('Connection could not be established')
        ->and($settings->mailDeliveryFailing())->toBeTrue();

    $this->actingAs($this->admin)
        ->get(route('admin.showcase-mail'))
        ->assertInertia(fn (Assert $page) => $page->where('showcase.state', 'failing')->where('showcase.errorHint', fn (string $hint) => str_contains($hint, 'Nothing answered') || str_contains($hint, 'Nessun')));
});

test('a failure on the platform mailer goes to the log, not onto a server that was never used', function () {
    ShowcaseMailSettings::factory()->create();
    Log::spy();

    (new DemoRequestMail('Anna Ferrari', 'anna@example.test', null, null, null, 'it', '18 nov 2026, 10:30'))
        ->failed(new TransportException('Connection refused'));

    Log::shouldHaveReceived('warning')->once();

    expect(ShowcaseMailSettings::current()->mail_error)->toBeNull();
});

test('the request leaves through the own server only after it has proved itself', function () {
    ShowcaseMailSettings::factory()->withServer()->create();

    $mail = new DemoRequestMail('Anna Ferrari', 'anna@example.test', null, null, null, 'it', '18 nov 2026, 10:30');

    expect($mail->envelope()->from->address)->toBe(config('mail.from.address'));

    ShowcaseMailSettings::query()->update(['mail_verified_at' => now()]);

    expect($mail->envelope()->from->address)->toBe('vetrina@example.com');
});
