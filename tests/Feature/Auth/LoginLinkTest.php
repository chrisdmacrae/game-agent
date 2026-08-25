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

test('the sign-in email is branded, not Laravel-branded', function () {
    $url = route('login.verify', 'a-plain-token');

    $mail = new LoginLinkMail($url);

    // Fixed copy: the subject must never render whatever APP_NAME happens to be.
    expect($mail->envelope()->subject)->toBe('Your sign-in link');

    $html = $mail->render();
    $text = preg_replace('/\s+/', ' ', strip_tags($html));

    expect($text)
        ->toContain('BUILD/YOUR/BUILD')
        ->toContain('Sign in to Build Your Build')
        ->toContain("This link signs you in once and expires in 15 minutes. If you didn't request it, ignore this email.")
        ->not->toContain('All rights reserved');

    expect($html)
        ->toContain('href="'.$url.'"')
        ->toContain('#2de1c2')        // the teal primary button
        ->toContain('color: #0a0d11') // near-black label on top of it
        ->not->toContain('laravel.com');
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

/*
|--------------------------------------------------------------------------
| Landing after sign-in
|--------------------------------------------------------------------------
|
| auth/Verify consumes the token over XHR. A plain redirect would make that
| XHR follow the response, and the intended URL is not always an Inertia page
| -- Passport's /oauth/authorize renders Blade -- so the answer is an Inertia
| location, which the client turns into a real browser visit.
|
*/

test('consuming a login link over xhr answers with an inertia location', function () {
    ['plainToken' => $token] = LoginLink::generateFor('exile@example.com');

    $this->post(route('login.verify.store', $token), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('my-builds'));

    $this->assertAuthenticated();
});

test('an intended oauth authorize url survives the magic link sign-in', function () {
    ['plainToken' => $token] = LoginLink::generateFor('exile@example.com');

    // What redirect()->guest() puts in the session when a guest hits
    // /oauth/authorize, which renders Blade rather than an Inertia page.
    $intended = url('/oauth/authorize?client_id=abc-123&response_type=code&scope=');

    $this->withSession(['url.intended' => $intended])
        ->post(route('login.verify.store', $token), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', $intended);

    $this->assertAuthenticated();

    // Pulled, not left behind for the next guarded request to reuse.
    expect(session()->has('url.intended'))->toBeFalse();
});

test('the signed-in toast is flashed so it survives the full page visit', function () {
    ['plainToken' => $token] = LoginLink::generateFor('exile@example.com');

    $this->post(route('login.verify.store', $token), [], ['X-Inertia' => 'true'])
        ->assertStatus(409);

    // Inertia flash data is session-backed, so the browser's next request --
    // the location visit -- picks it up and fires the toast on load.
    expect(session('inertia.flash_data'))
        ->toBe(['toast' => ['type' => 'success', 'message' => 'Signed in.']]);
});

test('login link requests are rate limited', function () {
    Mail::fake();

    foreach (range(1, 5) as $i) {
        $this->post(route('login-link.store'), ['email' => 'exile@example.com'])->assertRedirect();
    }

    $this->post(route('login-link.store'), ['email' => 'exile@example.com'])
        ->assertStatus(429);
});
