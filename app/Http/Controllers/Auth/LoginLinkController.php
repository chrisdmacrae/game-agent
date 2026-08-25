<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LoginLinkMail;
use App\Models\LoginLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Passwordless sign-in, in the three states scope §3.3 describes: request,
 * sent, verifying.
 */
class LoginLinkController extends Controller
{
    /**
     * The session key holding the address the link went to, so the sent screen
     * can echo it and offer a resend.
     */
    public const SESSION_EMAIL = 'login_link_email';

    /**
     * Email a single-use sign-in link. The response is identical whether or not
     * an account exists, so it cannot be used to enumerate accounts.
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
        ]);

        // A bad address is a toast on the request screen, not an inline field
        // error on a redirect (scope §3.3).
        if ($validator->fails()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('That does not look like an email address.'),
            ]);

            return back();
        }

        $email = Str::lower($validator->validated()['email']);

        ['plainToken' => $plainToken] = LoginLink::generateFor($email);

        Mail::to($email)->send(new LoginLinkMail(
            route('login.verify', $plainToken),
        ));

        $request->session()->put(self::SESSION_EMAIL, $email);

        return to_route('login.sent')
            ->with('status', "We've emailed you a sign-in link. It expires in 15 minutes.");
    }

    /**
     * The "check your inbox" screen. Reached only after a request: a direct
     * visit has no address to echo.
     */
    public function sent(Request $request): RedirectResponse|Response
    {
        $email = $request->session()->get(self::SESSION_EMAIL);

        if (! is_string($email) || $email === '') {
            return to_route('login');
        }

        return Inertia::render('auth/Sent', [
            'email' => $email,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * The verifying screen. This GET must not consume the token: mail scanners
     * and link previewers fetch the URL before the human ever clicks it. The
     * page posts to consume() instead.
     */
    public function verify(string $token): Response
    {
        return Inertia::render('auth/Verify', [
            'token' => $token,
            'action' => route('login.verify.store', $token),
        ]);
    }

    /**
     * Sign in via an emailed link. Clicking the link proves ownership of the
     * email address, so the account is created and verified on first use.
     */
    public function consume(Request $request, string $token): RedirectResponse
    {
        $link = LoginLink::findValidByToken($token);

        if ($link === null) {
            return to_route('login')->withErrors([
                'email' => 'This sign-in link is invalid or has expired. Request a new one.',
            ]);
        }

        $link->markConsumed();

        $user = User::firstOrCreate(
            ['email' => $link->email],
            [
                'name' => Str::before($link->email, '@'),
                'handle' => $this->availableHandle($link->email),
            ],
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_EMAIL);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signed in.')]);

        return redirect()->intended(route('my-builds'));
    }

    /**
     * A slug-ish handle derived from the address, suffixed until it is free.
     */
    protected function availableHandle(string $email): string
    {
        $base = Str::slug(Str::before($email, '@')) ?: 'player';
        $handle = $base;
        $suffix = 1;

        while (User::query()->where('handle', $handle)->exists()) {
            $handle = $base.'-'.(++$suffix);
        }

        return $handle;
    }
}
