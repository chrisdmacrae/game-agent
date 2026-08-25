<?php

use App\Mail\LoginLinkMail;
use App\Models\LoginLink;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('the login page renders the magic link form', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Login'));
});

test('requesting a login link emails a single-use link', function () {
    Mail::fake();

    $this->post(route('login-link.store'), ['email' => 'Exile@Example.com'])
        ->assertRedirect()
        ->assertSessionHas('status');

    Mail::assertSent(LoginLinkMail::class, fn (LoginLinkMail $mail) => $mail->hasTo('exile@example.com'));

    $link = LoginLink::sole();

    expect($link->email)->toBe('exile@example.com')
        ->and($link->consumed_at)->toBeNull()
        ->and($link->expires_at->isFuture())->toBeTrue();
});

test('the emailed url contains the plain token, not the stored hash', function () {
    Mail::fake();

    $this->post(route('login-link.store'), ['email' => 'exile@example.com']);

    Mail::assertSent(LoginLinkMail::class, function (LoginLinkMail $mail) {
        expect($mail->url)->not->toContain(LoginLink::sole()->token);

        return true;
    });
});

test('consuming a login link creates, verifies, and signs in a new user', function () {
    ['plainToken' => $token] = LoginLink::generateFor('new-exile@example.com');

    $this->get(route('login-link.consume', $token))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $user = User::sole();

    expect($user->email)->toBe('new-exile@example.com')
        ->and($user->name)->toBe('new-exile')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();
});

test('consuming a login link signs in an existing user without creating another', function () {
    $user = User::factory()->create(['email' => 'exile@example.com']);

    ['plainToken' => $token] = LoginLink::generateFor('exile@example.com');

    $this->get(route('login-link.consume', $token))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    expect(User::count())->toBe(1);
});

test('a login link can only be used once', function () {
    ['plainToken' => $token] = LoginLink::generateFor('exile@example.com');

    $this->get(route('login-link.consume', $token))->assertRedirect(route('dashboard'));

    $this->post(route('logout'));

    $this->get(route('login-link.consume', $token))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an expired login link is rejected', function () {
    ['plainToken' => $token] = LoginLink::generateFor('exile@example.com');

    LoginLink::sole()->update(['expires_at' => now()->subMinute()]);

    $this->get(route('login-link.consume', $token))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an unknown token is rejected', function () {
    $this->get(route('login-link.consume', 'not-a-real-token'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login link requests are rate limited', function () {
    Mail::fake();

    foreach (range(1, 5) as $i) {
        $this->post(route('login-link.store'), ['email' => 'exile@example.com'])->assertRedirect();
    }

    $this->post(route('login-link.store'), ['email' => 'exile@example.com'])
        ->assertStatus(429);
});
