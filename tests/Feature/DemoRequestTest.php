<?php

use App\Mail\DemoRequestMail;
use App\Models\ShowcaseMailSettings;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    ShowcaseMailSettings::factory()->create(['recipient' => 'demo@atrio.test']);
});

/**
 * The form of the home page as the visitor sends it.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function demoForm(array $changes = []): array
{
    return array_merge([
        'name' => 'Anna Ferrari',
        'email' => 'anna@example.test',
        'business' => 'Studio Ferrari',
        'plan' => 'Pro',
        'message' => "Ho tre operatori.\nVorrei sapere come funziona la lista d'attesa.",
        'privacy' => true,
        'website' => '',
    ], $changes);
}

test('a demo request is queued to the recipient with everything the visitor wrote', function () {
    $this->postJson(route('demo-request.store'), demoForm())
        ->assertOk()
        ->assertJson(['sent' => true]);

    Mail::assertQueued(DemoRequestMail::class, fn (DemoRequestMail $mail) => $mail->hasTo('demo@atrio.test')
        && $mail->requesterName === 'Anna Ferrari'
        && $mail->requesterEmail === 'anna@example.test'
        && $mail->requesterBusiness === 'Studio Ferrari'
        && $mail->requestedPlan === 'Pro'
        && $mail->requesterMessage === "Ho tre operatori.\nVorrei sapere come funziona la lista d'attesa."
        && $mail->pageLanguage === app()->getLocale());

    Mail::assertQueuedCount(1);
});

test('the optional fields can be left out', function () {
    $this->postJson(route('demo-request.store'), demoForm(['business' => '', 'plan' => '', 'message' => '']))->assertOk();

    Mail::assertQueued(DemoRequestMail::class, fn (DemoRequestMail $mail) => $mail->requesterBusiness === null
        && $mail->requestedPlan === null
        && $mail->requesterMessage === null);
});

test('the message of the request reaches the administrators, and the answer goes to the visitor', function () {
    $mail = new DemoRequestMail(
        requesterName: 'Anna Ferrari',
        requesterEmail: 'anna@example.test',
        requesterBusiness: 'Studio Ferrari',
        requestedPlan: 'Pro',
        requesterMessage: 'Come funziona la lista d\'attesa?',
        pageLanguage: 'it',
        consentGivenAt: '18 nov 2026, 10:30',
    );

    // Queued mailables travel serialised: the content has to survive the trip.
    $mail = unserialize(serialize($mail));

    $mail->assertHasSubject('Demo request from Anna Ferrari')
        ->assertHasReplyTo('anna@example.test', 'Anna Ferrari')
        ->assertSeeInHtml('Studio Ferrari')
        ->assertSeeInHtml('Pro')
        ->assertSeeInHtml("Come funziona la lista d'attesa?")
        ->assertSeeInHtml('Given on 18 nov 2026, 10:30');
});

test('without a recipient the form has nobody to send to', function () {
    ShowcaseMailSettings::query()->update(['recipient' => null]);

    $this->postJson(route('demo-request.store'), demoForm())->assertNotFound();

    Mail::assertNothingQueued();
});

test('the recipient of the environment is the fallback of the one saved by the administrator', function () {
    ShowcaseMailSettings::query()->update(['recipient' => null]);
    config(['app.demo_email' => 'ambiente@atrio.test']);

    $this->postJson(route('demo-request.store'), demoForm())->assertOk();

    Mail::assertQueued(DemoRequestMail::class, fn (DemoRequestMail $mail) => $mail->hasTo('ambiente@atrio.test'));
});

test('the privacy consent is required', function () {
    $this->postJson(route('demo-request.store'), demoForm(['privacy' => false]))->assertJsonValidationErrors('privacy');
    $this->postJson(route('demo-request.store'), array_diff_key(demoForm(), ['privacy' => true]))->assertJsonValidationErrors('privacy');

    Mail::assertNothingQueued();
});

test('name and a real email address are required', function () {
    $this->postJson(route('demo-request.store'), demoForm(['name' => '', 'email' => 'non-una-email']))
        ->assertJsonValidationErrors(['name', 'email']);

    Mail::assertNothingQueued();
});

test('the message has a limit', function () {
    $this->postJson(route('demo-request.store'), demoForm(['message' => str_repeat('a', 2001)]))->assertJsonValidationErrors('message');
    $this->postJson(route('demo-request.store'), demoForm(['message' => str_repeat('a', 2000)]))->assertOk();
});

test('a line break in the name cannot reach the headers of the email', function () {
    $this->postJson(route('demo-request.store'), demoForm(['name' => "Anna\r\nBcc: spia@example.test"]))->assertJsonValidationErrors('name');
    $this->postJson(route('demo-request.store'), demoForm(['business' => "Studio\nBcc: spia@example.test"]))->assertJsonValidationErrors('business');

    Mail::assertNothingQueued();
});

test('a robot that fills the trap field is told all is well and nothing is sent', function () {
    $this->postJson(route('demo-request.store'), demoForm(['website' => 'https://spam.example.test']))
        ->assertOk()
        ->assertJson(['sent' => true]);

    Mail::assertNothingQueued();
});

test('one address cannot flood the administrators', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('demo-request.store'), demoForm())->assertOk();
    }

    $this->postJson(route('demo-request.store'), demoForm())->assertTooManyRequests();

    Mail::assertQueuedCount(5);
});
