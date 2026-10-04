<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('the reset request and notification speak Italian', function () {
    Notification::fake();
    $user = User::factory()->create(['locale' => 'it']);

    $this->withSession(['locale' => 'it'])->post(route('password.request'), ['email' => $user->email])
        ->assertSessionHas('status', 'Se questo indirizzo appartiene a un account, riceverai un link per reimpostare la password.');

    Notification::assertSentTo($user, ResetPassword::class);
    $message = (new ResetPassword('test-token'))->toMail($user);
    expect($message->subject)->toBe('Reimposta la tua password');
    expect($message->actionText)->toBe('Reimposta password');
    expect($message->introLines)->toContain('Hai ricevuto questa email perché è stata richiesta la reimpostazione della password del tuo account.');
    expect($message->outroLines)->toContain('Se non hai richiesto la reimpostazione, non devi fare nulla.');
});

test('reset requests conceal existing unknown and recently contacted accounts', function (string $state, bool $json) {
    Notification::fake();
    $user = User::factory()->create();
    if ($state === 'recent') {
        $this->post(route('password.email'), ['email' => $user->email]);
    }
    $email = $state === 'unknown' ? 'unknown@example.test' : $user->email;
    $message = 'If this email belongs to an account, you will receive a password reset link.';

    if ($json) {
        $this->postJson(route('password.email'), ['email' => $email])
            ->assertOk()->assertExactJson(['message' => $message]);
    } else {
        $this->from(route('password.request'))->post(route('password.email'), ['email' => $email])
            ->assertRedirect(route('password.request'))->assertSessionHasNoErrors()
            ->assertSessionHas('status', $message);
    }

    if ($state === 'unknown') {
        Notification::assertNothingSent();
    } else {
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }
})->with(['existing', 'unknown', 'recent'])->with([true, false]);

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});
