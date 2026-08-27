<?php

namespace App\Mcp\Tools\Poe2;

use App\Concerns\UsesLinkedPoeAccount;
use App\Domain\Poe2\Ggg\CharacterBuildDiff;
use App\Domain\Poe2\Ggg\CharacterNormalizer;
use App\Domain\Poe2\Ggg\GggApiClient;
use App\Domain\Poe2\Validation\BuildRules;
use App\Models\Build;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class CompareCharacterToBuildTool extends Tool
{
    use UsesLinkedPoeAccount;

    protected string $name = 'compare_character_to_build';

    protected string $description = 'Diff one of the signed-in user\'s live Path of Exile 2 characters against a build, and get back exactly what differs: passive nodes the build takes that the character has not (and extra ones it has), skill gems and support gems that are missing or swapped, gear slots that are empty or hold a different item, and rare gear missing mods the build asks for. Give it a character name plus either build_id (a saved build) or an inline build definition. Use this to answer "how far am I from this build?" or "what should I upgrade next?" — the diff is the factual basis; explain the impact of each gap with the game data tools. Note that the character API exposes no computed stats, so resistances, DPS and EHP are never compared.';

    public function handle(
        Request $request,
        GggApiClient $api,
        CharacterNormalizer $normalizer,
        CharacterBuildDiff $diff,
    ): Response {
        $account = $this->linkedAccount($request->user());

        if ($account === null) {
            return $this->notLinked();
        }

        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:60',
            'build_id' => 'nullable|string|max:32',
        ], $this->optionalBuildRules()));

        $target = $this->targetBuild($request, $validated);

        if ($target instanceof Response) {
            return $target;
        }

        return $this->guarded(function () use ($account, $api, $normalizer, $diff, $validated, $target) {
            $character = $api->character($account, $validated['name']);

            if ($character === null) {
                return Response::error(
                    "No Path of Exile 2 character named '{$validated['name']}' is on this account. Call list_my_characters to see the available names.",
                );
            }

            return Response::json([
                'build' => $target['label'],
                ...$diff->compare($normalizer->normalize($character), $target['payload']),
            ]);
        });
    }

    /**
     * Resolve the build to compare against: a saved one by id, or an inline
     * definition for a build that is only in the conversation.
     *
     * @param  array<string, mixed>  $validated
     * @return array{label: array<string, mixed>, payload: array<string, mixed>}|Response
     */
    protected function targetBuild(Request $request, array $validated): array|Response
    {
        if (isset($validated['build_id'])) {
            // Drafts are readable by their owner only.
            $build = Build::query()
                ->visibleTo($request->user())
                ->where('public_id', $validated['build_id'])
                ->first();

            if ($build === null) {
                return Response::error("No saved build with id \"{$validated['build_id']}\".");
            }

            return [
                'label' => ['id' => $build->public_id, 'name' => $build->name, 'url' => $build->url()],
                'payload' => $build->build,
            ];
        }

        if (isset($validated['build']) && is_array($validated['build'])) {
            return ['label' => ['name' => 'inline build definition'], 'payload' => $validated['build']];
        }

        return Response::error('Pass build_id (a saved build) or an inline build definition to compare against.');
    }

    /**
     * The same build shape the other tools take, with `skills` relaxed to
     * optional — a comparison target may be gear-only or passives-only.
     *
     * @return array<string, mixed>
     */
    protected function optionalBuildRules(): array
    {
        $rules = BuildRules::rules('build.');
        $rules['build'] = 'nullable|array';
        $rules['build.skills'] = 'nullable|array';

        return $rules;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The character name, exactly as returned by list_my_characters.')->required(),
            'build_id' => $schema->string()->description('The public id of a saved build to compare against, e.g. "k3v9x2m8q1zr". Omit if passing an inline build.'),
            'build' => $schema->object([
                'class' => $schema->string(),
                'ascendancy' => $schema->string(),
                'level' => $schema->integer(),
                'skills' => $schema->array()->items(
                    $schema->object([
                        'gem' => $schema->string()->required(),
                        'supports' => $schema->array()->items($schema->anyOf([
                            $schema->string(),
                            $schema->object(['name' => $schema->string()->required()]),
                        ])),
                    ]),
                ),
                'passives' => $schema->object([
                    'node_ids' => $schema->array()->items($schema->integer())->description('Required for a passive tree diff — names alone cannot be diffed.'),
                ]),
                'gear' => $schema->array()->items(
                    $schema->object([
                        'slot' => $schema->string()->enum(['helmet', 'body', 'gloves', 'boots', 'amulet', 'ring1', 'ring2', 'belt', 'weapon1', 'offhand1', 'weapon2', 'offhand2'])->required(),
                        'rarity' => $schema->string()->enum(['unique', 'rare', 'magic', 'normal'])->required(),
                        'name' => $schema->string(),
                        'mods' => $schema->array()->items($schema->string()),
                    ]),
                ),
            ])->description('An inline build to compare against, when it has not been saved. Same shape as validate_build. Ignored when build_id is given.'),
        ];
    }
}
