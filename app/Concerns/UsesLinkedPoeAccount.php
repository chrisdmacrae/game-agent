<?php

namespace App\Concerns;

use App\Domain\Poe2\Ggg\Exceptions\GggRateLimited;
use App\Domain\Poe2\Ggg\Exceptions\GggRequestFailed;
use App\Domain\Poe2\Ggg\Exceptions\PoeAccountDisconnected;
use App\Domain\Poe2\Ggg\GggOAuth;
use App\Models\PoeAccount;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Response;
use Throwable;

/**
 * Shared plumbing for the MCP tools that read a user's own PoE2 characters.
 *
 * All of them need the same two things: to stay unregistered unless the
 * integration is configured AND someone is signed in, and to fail with an
 * actionable message (naming the settings URL) rather than a raw HTTP error
 * when the account is not linked or the token has lapsed.
 */
trait UsesLinkedPoeAccount
{
    /**
     * Registered only for a signed-in user, and only where GGG credentials
     * exist — matching how save_build gates on Auth::check().
     */
    public function shouldRegister(): bool
    {
        return Auth::check() && app(GggOAuth::class)->enabled();
    }

    /**
     * The caller's linked GGG account, or null when there is nothing linked.
     */
    protected function linkedAccount(mixed $user): ?PoeAccount
    {
        if ($user === null) {
            return null;
        }

        return PoeAccount::query()->where('user_id', $user->getAuthIdentifier())->first();
    }

    protected function notLinked(): Response
    {
        return Response::error(
            'No Path of Exile account is linked to this user yet. Give them this link and ask '
            .'them to open it in their browser: '.route('settings.poe.redirect').' — it lands on '
            .'Grinding Gear Games\' consent screen, which cannot be completed from here, and '
            .'grants read-only access to their profile and character list. Call '
            .'connect_poe_account for the full explanation, and try this tool again once they '
            .'have approved it.',
        );
    }

    /**
     * Run a GGG API call, turning its failure modes into messages the
     * assistant can act on instead of exceptions.
     *
     * @param  callable(): Response  $callback
     */
    protected function guarded(callable $callback): Response
    {
        try {
            return $callback();
        } catch (PoeAccountDisconnected $e) {
            return Response::error(
                $e->getMessage().' Reconnect at '.route('profile.edit').'.',
            );
        } catch (GggRateLimited $e) {
            return Response::error($e->getMessage());
        } catch (GggRequestFailed $e) {
            return Response::error($e->getMessage());
        } catch (Throwable) {
            return Response::error('Could not reach the Path of Exile API. Try again shortly.');
        }
    }
}
