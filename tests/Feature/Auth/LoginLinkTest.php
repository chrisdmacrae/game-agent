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

test('requesting a login link emails a single-use link and shows the sent screen', function () {
    Mail::fake();

    $this->post(route('login-link.store'), ['email' => 'Exile@Example.com'])
        ->assertRedirect(route('login.sent'))
        ->assertSessionHas('status');

    Mail::assertSent(LoginLinkMail::class, fn (LoginLinkMail $mail) => $mail->hasTo('exile@example.com'));

    $link = LoginLink::sole();

    expect($link->email)->toBe('exile@example.com')
        ->and($link->consumed_at)->toBeNull()
        ->and($link->expires_at->isFuture())->toBeTrue();

    $this->get(route('login.sent'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/Sent')
            ->where('email', 'exile@example.com')
        );
});

test('the sent screen redirects to the login page when no link was requested', function () {
    $this->get(route('login.sent'))->assertRedirect(route('login'));
});

test('an invalid email flashes a toast instead of an inline error', function () {
    Mail::fake();

    $this->from(route('login'))
        ->post(route('login-link.store'), ['email' => 'not-an-email'])
        ->assertRedirect(route('login'))
        ->assertSessionHasNoErrors();

    Mail::assertNothingSent();

    expect(LoginLink::count())->toBe(0);
});

test('the emailed url points at the verify page and carries the plain token', function () {
    Mail::fake();

    $this->post(route('login-link.store'), ['email' => 'exile@example.com']);

    Mail::assertSent(LoginLinkMail::class, function (LoginLinkMail $mail) {
        expect($mail->url)->not->toContain(LoginLink::sole()->token)
            ->and($mail->url)->toContain('/login/verify/');

        return true;
    });
});

test('opening the verify page does not consume the token', function () {
    ['plainToken' => $token] = LoginLink::generateFor('exile@example.com');

    // Mail scanners fetch emailed links; the GET must survive that.
    $this->get(route('login.verify', $token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Verify')->where('token', $token));

    expect(LoginLink::sole()->consumed_at)->toBeNull();
    $this->assertGuest();

    $this->post(route('login.verify.store', $token))->assertRedirect(route('my-builds'));

    $this->assertAuthenticated();
    expect(LoginLink::sole()->consumed_at)->not->toBeNull();
});

test('consuming a login link creates, verifies, and signs in a new user', function () {
    ['plainToken' => $token] = LoginLink::generateFor('new-exile@example.com');

    $this->post(route('login.verify.store', $token))
        ->assertRedirect(route('my-builds'));

    $this->assertAuthenticated();

    $user = User::sole();

    expect($user->email)->toBe('new-exile@example.com')
        ->and($user->name)->toBe('new-exile')
        ->and($user->handle)->toBe('new-exile')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();
});

test('consuming a login link signs in an existing user without creating another', function () {
    $user = User::factory()->create(['email' => 'exile@example.com']);

    ['plainToken' => $token] = LoginLink::generateFor('exile@example.com');

    $this->post(route('login.verify.store', $token))
        ->assertRedirect(route('my-builds'));

    $this->assertAuthenticatedAs($user);

    expect(User::count())->toBe(1);
});

test('a login link can only be used once', function () {
    ['plainToken' => $token] = LoginLink::generateFor('exile@example.com');

    $this->post(route('login.verify.store', $token))->assertRedirect(route('my-builds'));

    $this->post(route('logout'));

    $this->post(route('login.verify.store', $token))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an expired login link is rejected', function () {
    ['plainToken' => $token] = LoginLink::generateFor('exile@example.com');

    LoginLink::sole()->update(['expires_at' => now()->subMinute()]);

    $this->post(route('login.verify.store', $token))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an unknown token is rejected', function () {
    $this->post(route('login.verify.store', 'not-a-real-token'))
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
