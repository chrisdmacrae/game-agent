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
use Illuminate\Support\Str;

class LoginLinkController extends Controller
{
    /**
     * Email a single-use sign-in link. The response is identical whether or not
     * an account exists, so it cannot be used to enumerate accounts.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $email = Str::lower($validated['email']);

        ['plainToken' => $plainToken] = LoginLink::generateFor($email);

        Mail::to($email)->send(new LoginLinkMail(
            route('login-link.consume', $plainToken),
        ));

        return back()->with('status', "We've emailed you a sign-in link. It expires in 15 minutes.");
    }

    /**
     * Sign in via an emailed link. Clicking the link proves ownership of the
     * email address, so the account is created and verified on first use.
     */
    public function consume(string $token): RedirectResponse
    {
        $link = LoginLink::findValidByToken($token);

        if ($link === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'This sign-in link is invalid or has expired. Request a new one.',
            ]);
        }

        $link->markConsumed();

        $user = User::firstOrCreate(
            ['email' => $link->email],
            ['name' => Str::before($link->email, '@')],
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);

        session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
