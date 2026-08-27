<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Poe2\Ggg\Exceptions\GggRequestFailed;
use App\Domain\Poe2\Ggg\GggApiClient;
use App\Domain\Poe2\Ggg\GggOAuth;
use App\Http\Controllers\Controller;
use App\Models\PoeAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

/**
 * Links a user's Grinding Gear Games account so the PoE2 MCP tools can read
 * the characters they actually play.
 *
 * The whole flow is dark unless credentials are configured — GGG is not
 * accepting new developer applications, and a half-present Connect button that
 * dead-ends at their error page is worse than no button.
 */
class PoeConnectionController extends Controller
{
    protected const STATE_KEY = 'poe_oauth_state';

    protected const VERIFIER_KEY = 'poe_oauth_verifier';

    public function __construct(protected GggOAuth $oauth)
    {
        // Not middleware: the check has to hold for the callback too, which
        // GGG calls with its own query string.
        abort_unless($this->oauth->enabled(), 404);
    }

    /**
     * Send the browser to pathofexile.com for consent.
     *
     * The state and the PKCE verifier are held in the session — the verifier
     * must never travel with the authorization request, that is the whole
     * point of PKCE.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $state = bin2hex(random_bytes(16));
        $verifier = $this->oauth->newVerifier();

        $request->session()->put(self::STATE_KEY, $state);
        $request->session()->put(self::VERIFIER_KEY, $verifier);

        return redirect()->away($this->oauth->authorizationUrl($state, $verifier));
    }

    /**
     * Consume the authorization code. GGG expires codes after 30 seconds, so
     * the exchange runs inline here rather than on a queue.
     */
    public function callback(Request $request, GggApiClient $api): RedirectResponse
    {
        $state = $request->session()->pull(self::STATE_KEY);
        $verifier = $request->session()->pull(self::VERIFIER_KEY);

        if ($request->filled('error')) {
            $reason = $request->string('error_description')->value()
                ?: $request->string('error')->value();

            return $this->failed('Path of Exile did not grant access: '.$reason);
        }

        if (! is_string($state) || ! is_string($verifier) || ! hash_equals($state, (string) $request->query('state'))) {
            return $this->failed('That connection attempt could not be verified. Please try again.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->failed('Path of Exile did not return an authorization code.');
        }

        try {
            $tokens = $this->oauth->exchange($code, $verifier);
            $profile = $api->profileWithToken($tokens['access_token']);
        } catch (GggRequestFailed $e) {
            return $this->failed($e->getMessage());
        } catch (Throwable) {
            return $this->failed('Could not reach Path of Exile to finish connecting. Please try again.');
        }

        $account = PoeAccount::firstOrNew(['user_id' => $request->user()->getAuthIdentifier()]);
        $account->fillTokens($tokens);
        $account->ggg_uuid = is_string($profile['uuid'] ?? null) ? $profile['uuid'] : null;
        $account->ggg_name = is_string($profile['name'] ?? null) ? $profile['name'] : 'Unknown';
        $account->connected_at = now();
        $account->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Connected :name on Path of Exile.', ['name' => $account->ggg_name]),
        ]);

        return redirect()->route('profile.edit');
    }

    /**
     * Unlink. Revocation is attempted but never blocks: the local row is what
     * grants us access, so removing it is the guarantee the user wants.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $account = $request->user()->poeAccount;

        if ($account !== null) {
            $this->oauth->revoke($account->access_token);
            $account->delete();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Disconnected your Path of Exile account.'),
        ]);

        return redirect()->route('profile.edit');
    }

    protected function failed(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return redirect()->route('profile.edit');
    }
}
