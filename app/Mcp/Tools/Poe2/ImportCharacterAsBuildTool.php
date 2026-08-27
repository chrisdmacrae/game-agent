<?php

namespace App\Mcp\Tools\Poe2;

use App\Concerns\UsesLinkedPoeAccount;
use App\Domain\Builds\BuildPayload;
use App\Domain\Poe2\Ggg\CharacterNormalizer;
use App\Domain\Poe2\Ggg\GggApiClient;
use App\Domain\Poe2\Poe2Context;
use App\Domain\Poe2\Validation\BuildValidator;
use App\Models\Build;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent(false)]
class ImportCharacterAsBuildTool extends Tool
{
    use UsesLinkedPoeAccount;

    protected string $name = 'import_character_as_build';

    protected string $description = 'Turn one of the signed-in user\'s live Path of Exile 2 characters into a saved build page they own, so they can edit it and publish it as a guide. Imports the gear, skill gems with supports, and the allocated passive tree exactly as the character has them. Always saved as a DRAFT visible only to the owner: the import carries no stats, guide text or reasoning, and those are what make a build worth publishing — write a guide with save_build (passing the returned id) or let the user finish it in the web editor. Give the user the returned URL.';

    public function handle(
        Request $request,
        GggApiClient $api,
        CharacterNormalizer $normalizer,
        Poe2Context $context,
        BuildValidator $validator,
    ): Response {
        $user = $request->user();
        $account = $this->linkedAccount($user);

        if ($account === null) {
            return $this->notLinked();
        }

        $validated = $request->validate([
            'name' => 'required|string|max:60',
            'build_name' => 'nullable|string|max:120',
        ]);

        return $this->guarded(function () use ($account, $api, $normalizer, $context, $validator, $validated, $user) {
            $character = $api->character($account, $validated['name']);

            if ($character === null) {
                return Response::error(
                    "No Path of Exile 2 character named '{$validated['name']}' is on this account. Call list_my_characters to see the available names.",
                );
            }

            $normalized = $normalizer->normalize($character);
            $definition = BuildPayload::normalize($normalized['build']);

            if (($definition['skills'] ?? []) === []) {
                return Response::error(
                    "'{$validated['name']}' has no skill gems the API can read, so there is nothing to import. This usually means the character has not been played since gems were socketed.",
                );
            }

            $version = $context->version();

            $build = new Build([
                'user_id' => $user->getAuthIdentifier(),
                'game_id' => $version->game_id,
                'game_version_id' => $version->id,
                'name' => $validated['build_name'] ?? $normalized['name'],
                'summary' => $this->summaryFor($normalized),
                'build' => $definition,
                'validation' => $validator->validate($definition),
                // Never public: an import has no guide, no stats and no
                // reasoning, which is most of what a published build is.
                'visibility' => Build::VISIBILITY_DRAFT,
            ]);

            $build->syncPromotedFields();
            $build->save();

            $account->update(['last_synced_at' => now()]);

            return Response::json([
                'id' => $build->public_id,
                'url' => $build->url(),
                'visibility' => $build->visibility,
                'build' => $definition,
                'validation' => $build->validation,
                'note' => 'Imported as a draft, visible only to its owner. It has no stats, guide or skill roles yet — call save_build with this id to add them, or send the user to the URL to finish it in the web editor. Validation violations here describe the live character, so check them with the user before "fixing" anything.',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function summaryFor(array $normalized): string
    {
        $build = $normalized['build'];

        return trim(sprintf(
            'Imported from %s, a level %s %s in %s.',
            $normalized['name'] ?? 'a character',
            $build['level'] ?? '?',
            $build['ascendancy'] ?? $build['class'] ?? 'character',
            $normalized['league'] ?? 'Path of Exile 2',
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The character name, exactly as returned by list_my_characters.')->required(),
            'build_name' => $schema->string()->description('Title for the saved build page. Defaults to the character name.'),
        ];
    }
}
