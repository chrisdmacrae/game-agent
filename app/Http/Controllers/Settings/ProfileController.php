<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Seo\PageMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Mail\EmailChangeMail;
use App\Models\Build;
use App\Models\PendingEmailChange;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            new PageMeta(title: 'Settings', noindex: true),
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'profile' => [
                'name' => $user->name,
                'handle' => $user->handle,
                'discord_username' => $user->discord_username,
                'bio' => $user->bio,
                'email' => $user->email,
            ],
            'pendingEmail' => PendingEmailChange::query()
                ->where('user_id', $user->id)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->value('email'),
            'buildCounts' => $this->buildCounts($user),
        ]);
    }

    /**
     * What the delete-account dialog has to name before it asks (scope §3.9).
     *
     * @return array{published: int, drafts: int}
     */
    protected function buildCounts(User $user): array
    {
        $counts = Build::query()
            ->where('user_id', $user->id)
            ->selectRaw('visibility, count(*) as total')
            ->groupBy('visibility')
            ->pluck('total', 'visibility');

        return [
            'published' => (int) $counts->get(Build::VISIBILITY_PUBLIC, 0),
            'drafts' => (int) $counts->get(Build::VISIBILITY_DRAFT, 0),
        ];
    }

    /**
     * Update the user's profile information.
     *
     * Changing the email does not swap it: a confirmation link goes to the new
     * address and the old one keeps working until it is used (scope §3.9).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $newEmail = Str::lower($validated['email']);
        unset($validated['email']);

        $user->fill($validated)->save();

        if ($newEmail !== Str::lower($user->email)) {
            ['plainToken' => $plainToken] = PendingEmailChange::generateFor($user, $newEmail);

            Mail::to($newEmail)->send(new EmailChangeMail(
                route('profile.email.confirm', $plainToken),
            ));

            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Check :email for a confirmation link. Your current address keeps working until you use it.', ['email' => $newEmail]),
            ]);

            return to_route('profile.edit');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     *
     * Accounts have no password, so deletion is confirmed by re-typing the
     * account's email address.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|in:'.$request->user()->email,
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
