<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Seo\PageMeta;
use App\Http\Controllers\Controller;
use App\Models\PendingEmailChange;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Confirming an account email change (scope §3.9). The token is the proof, so
 * the link works from any browser — including the phone the new inbox is on.
 */
class EmailChangeController extends Controller
{
    /**
     * As with sign-in links, the GET must not consume the token: mail scanners
     * fetch it first. The page posts to update() instead.
     */
    public function show(string $token): Response
    {
        return Inertia::render('auth/Verify', [
            new PageMeta(title: 'Confirming your new email', noindex: true),
            'token' => $token,
            'action' => route('profile.email.confirm.store', $token),
            'title' => 'Confirming your new email',
        ]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $change = PendingEmailChange::findValidByToken($token);

        if ($change === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('That confirmation link is invalid or has expired. Request a new one.'),
            ]);

            return to_route('profile.edit');
        }

        $taken = User::query()
            ->where('email', $change->email)
            ->whereKeyNot($change->user_id)
            ->exists();

        if ($taken) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('That address now belongs to another account.'),
            ]);

            return to_route('profile.edit');
        }

        $change->markConsumed();

        $user = $change->user;

        $user->forceFill([
            'email' => $change->email,
            'email_verified_at' => now(),
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Email updated to :email.', ['email' => $change->email]),
        ]);

        return to_route('profile.edit');
    }
}
