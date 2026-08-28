<?php

namespace App\Mcp\Tools\Poe2;

use App\Concerns\UsesLinkedPoeAccount;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The handoff into Grinding Gear Games' consent screen.
 *
 * The consent itself cannot happen here: OAuth requires the user's own browser
 * on pathofexile.com, and a token minted without them seeing GGG's screen is
 * exactly what OAuth exists to prevent. So this tool does everything around
 * it — reports whether an account is linked, and hands back one link that
 * lands on GGG's consent screen.
 */
#[IsReadOnly]
class ConnectPoeAccountTool extends Tool
{
    use UsesLinkedPoeAccount;

    protected string $name = 'connect_poe_account';

    protected string $description = 'Check whether the signed-in user has linked their Path of Exile (Grinding Gear Games) account, and get the link that connects one. Call this when a character tool reports no linked account, or when the user asks to connect, reconnect or switch Path of Exile accounts. Give them the returned connect_url and tell them to open it: it lands on Grinding Gear Games\' own consent screen, which has to happen in their browser — it cannot be completed from here. Access is read-only and covers their profile and character list. Once they have approved it, call list_my_characters again.';

    public function handle(Request $request): Response
    {
        $account = $this->linkedAccount($request->user());
        $connectUrl = route('settings.poe.redirect');

        if ($account !== null) {
            return Response::json([
                'connected' => true,
                'account' => $account->ggg_name,
                'connected_at' => $account->connected_at->toIso8601String(),
                'connect_url' => $connectUrl,
                'note' => "This user's Path of Exile account ({$account->ggg_name}) is already linked, so the character tools will work. Opening connect_url again re-authorises it, which is what to do if they want to switch to a different Path of Exile account. They can disconnect it from their account settings.",
            ]);
        }

        return Response::json([
            'connected' => false,
            'connect_url' => $connectUrl,
            'grants' => ['account:profile', 'account:characters'],
            'note' => 'No Path of Exile account is linked yet. Give the user connect_url and ask them to open it in their browser: it goes to Grinding Gear Games\' consent screen, which cannot be completed from this conversation. It grants read-only access to their profile and character list, and nothing in game can be changed. If they are not signed in to the site in that browser they will be asked to sign in first and then continue automatically. Call list_my_characters once they say they have approved it.',
        ]);
    }
}
