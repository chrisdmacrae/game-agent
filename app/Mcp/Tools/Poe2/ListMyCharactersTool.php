<?php

namespace App\Mcp\Tools\Poe2;

use App\Concerns\UsesLinkedPoeAccount;
use App\Domain\Poe2\Ggg\CharacterNormalizer;
use App\Domain\Poe2\Ggg\GggApiClient;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListMyCharactersTool extends Tool
{
    use UsesLinkedPoeAccount;

    protected string $name = 'list_my_characters';

    protected string $description = 'List the signed-in user\'s own Path of Exile 2 characters from their linked Grinding Gear Games account: name, class, level and league. Use this first when the user asks about "my character", wants their current character reviewed, or wants it compared against a build — then pass a name to get_my_character or compare_character_to_build. Requires the user to have connected their Path of Exile account in account settings; if they have not, this returns the URL to send them to. Character data reflects the last time the game saved the character, not live state.';

    public function handle(Request $request, GggApiClient $api, CharacterNormalizer $normalizer): Response
    {
        $account = $this->linkedAccount($request->user());

        if ($account === null) {
            return $this->notLinked();
        }

        return $this->guarded(function () use ($account, $api, $normalizer) {
            $characters = array_map(
                fn (array $character) => $normalizer->summarize($character),
                $api->characters($account),
            );

            return Response::json([
                'account' => $account->ggg_name,
                'realm' => GggApiClient::REALM,
                'characters' => $characters,
                'note' => $characters === []
                    ? 'This account has no Path of Exile 2 characters. It may only have Path of Exile 1 characters, which this server does not read.'
                    : 'Pass a name to get_my_character for the full character, or to compare_character_to_build to diff it against a saved build.',
            ]);
        });
    }
}
