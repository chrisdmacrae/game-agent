<?php

namespace App\Mcp\Tools\Poe2;

use App\Concerns\UsesLinkedPoeAccount;
use App\Domain\Poe2\Ggg\CharacterNormalizer;
use App\Domain\Poe2\Ggg\GggApiClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetMyCharacterTool extends Tool
{
    use UsesLinkedPoeAccount;

    protected string $name = 'get_my_character';

    protected string $description = 'Read one of the signed-in user\'s own Path of Exile 2 characters in full: class and ascendancy, level, equipped gear with its mods and runes, skill gems with their socketed supports, and every allocated passive node. The result uses the same shape as a build definition, so it can be handed straight to validate_build or compared field for field with a saved build. Get the name from list_my_characters first. The API exposes no computed stats — there are no resistances, DPS or EHP numbers here, so ask the user for those rather than guessing.';

    public function handle(Request $request, GggApiClient $api, CharacterNormalizer $normalizer): Response
    {
        $account = $this->linkedAccount($request->user());

        if ($account === null) {
            return $this->notLinked();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:60',
        ]);

        return $this->guarded(function () use ($account, $api, $normalizer, $validated) {
            $character = $api->character($account, $validated['name']);

            if ($character === null) {
                return Response::error(
                    "No Path of Exile 2 character named '{$validated['name']}' is on this account. Call list_my_characters to see the available names.",
                );
            }

            return Response::json($normalizer->normalize($character));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The character name, exactly as returned by list_my_characters.')->required(),
        ];
    }
}
