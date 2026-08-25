<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\PobExporter;
use App\Domain\Poe2\Poe2Context;
use App\Domain\Poe2\Validation\BuildRules;
use App\Domain\Poe2\Validation\BuildValidator;
use App\Models\SavedBuild;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent(false)]
class SaveBuildTool extends Tool
{
    protected string $name = 'save_build';

    protected string $description = 'Save a finished build to the logged-in user\'s account and get a permanent shareable web page URL for it. Pass the id of a previously saved build to update it in place instead of creating a new page. Give the returned URL to the user — the page shows the build (skills, supports, passives, defenses) plus your guide text. Validate the build first with validate_build and fix violations; the validation result is stored and displayed on the page. Include a thorough guide_markdown: it is the main content readers see.';

    /**
     * Only available on the authenticated MCP endpoint: saved builds belong to a user.
     */
    public function shouldRegister(): bool
    {
        return Auth::check();
    }

    public function handle(Request $request, Poe2Context $context, BuildValidator $validator, PobExporter $exporter): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Saving builds requires the authenticated MCP endpoint. Connect to '.route('mcp.poe2.user').' and sign in.');
        }

        $validated = $request->validate(array_merge([
            'id' => 'nullable|string|max:32',
            'name' => 'required|string|max:120',
            'summary' => 'nullable|string|max:500',
            'guide_markdown' => 'nullable|string|max:30000',
        ], BuildRules::rules('build.')));

        $version = $context->version();

        $attributes = [
            'game_id' => $version->game_id,
            'game_version_id' => $version->id,
            'name' => $validated['name'],
            'summary' => $validated['summary'] ?? null,
            'guide_markdown' => $validated['guide_markdown'] ?? null,
            'build' => $validated['build'],
            'validation' => $validator->validate($validated['build']),
        ];

        if (isset($validated['id'])) {
            $build = SavedBuild::query()
                ->where('public_id', $validated['id'])
                ->where('user_id', $user->getAuthIdentifier())
                ->first();

            if ($build === null) {
                return Response::error("No build with id '{$validated['id']}' belongs to the signed-in user. Omit id to save a new build.");
            }

            $build->update($attributes);
        } else {
            $build = SavedBuild::create($attributes + ['user_id' => $user->getAuthIdentifier()]);
        }

        return Response::json([
            'id' => $build->public_id,
            'url' => $build->url(),
            'validation' => $build->validation,
            'pob_code' => $exporter->code($build),
            'note' => 'Share the url with the user. The pob_code imports into Path of Building (PoE2 fork). The build was validated automatically; if violations are listed, fix them and save again with this id to update the same page.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The id of a previously saved build to update in place (from an earlier save_build response). Omit to save a new build.'),
            'name' => $schema->string()->description('Build title, e.g. "The Untouchable Bell — Ab Aeterno Roll-Caster".')->required(),
            'summary' => $schema->string()->description('One-or-two sentence description shown under the title.'),
            'guide_markdown' => $schema->string()->description('The full build guide as markdown: concept, why the pieces fit, gear priorities, leveling notes. This is the main page content.'),
            'build' => $schema->object([
                'class' => $schema->string(),
                'ascendancy' => $schema->string(),
                'level' => $schema->integer(),
                'skills' => $schema->array()->items(
                    $schema->object([
                        'gem' => $schema->string()->required(),
                        'supports' => $schema->array()->items($schema->string()),
                    ]),
                )->required(),
                'spirit_available' => $schema->integer(),
                'passives' => $schema->object([
                    'keystones' => $schema->array()->items($schema->string()),
                    'notables' => $schema->array()->items($schema->string()),
                    'ascendancy_nodes' => $schema->array()->items($schema->string())->description('Ascendancy passive names taken (must belong to the build\'s ascendancy).'),
                    'points_used' => $schema->integer(),
                    'node_ids' => $schema->array()->items($schema->integer())->description('Exact allocated passive node ids (from search_passives). Recommended: enables the build page tree render.'),
                    'granted_nodes' => $schema->array()->items(
                        $schema->object([
                            'node_id' => $schema->integer()->required(),
                            'source' => $schema->string()->enum(['instilled_amulet', 'unique_jewel', 'ascendancy_mechanic'])->required(),
                            'detail' => $schema->string()->description('e.g. the jewel name or the three distilled emotions.'),
                        ]),
                    )->description('Nodes allocated WITHOUT tree pathing via special mechanics: instilled amulets (notables only), unique jewels (e.g. From Nothing), or ascendancy mechanics (e.g. Oracle\'s Entwined Realities). All other node_ids must form a contiguous path from the class start.'),
                ]),

                'gear' => $schema->array()->items(
                    $schema->object([
                        'slot' => $schema->string()->enum(['helmet', 'body', 'gloves', 'boots', 'amulet', 'ring1', 'ring2', 'belt', 'weapon1', 'offhand1', 'weapon2', 'offhand2'])->required(),
                        'rarity' => $schema->string()->enum(['unique', 'rare', 'magic', 'normal'])->required(),
                        'name' => $schema->string()->description('Unique item name (validated), or a label for rares.'),
                        'base' => $schema->string()->description('Base type, e.g. "Stellar Amulet".'),
                        'mods' => $schema->array()->items($schema->string())->description('Desired affixes for rare gear.'),
                        'instill' => $schema->object([
                            'notable' => $schema->string()->required(),
                            'emotions' => $schema->array()->items($schema->string())->description('The three distilled emotions used.'),
                        ])->description('Amulet only: the notable passive granted by instilling this amulet.'),
                    ]),
                )->description('Structured gear, one entry per slot. Required to substantiate granted_nodes claims (instilled amulets).'),
                'jewels' => $schema->array()->items(
                    $schema->object([
                        'name' => $schema->string()->required(),
                        'rarity' => $schema->string()->enum(['unique', 'rare', 'magic'])->required(),
                        'socket_node_id' => $schema->integer()->description('The tree jewel socket node id this jewel sits in.'),
                        'mods' => $schema->array()->items($schema->string()),
                    ]),
                )->description('Jewels socketed in tree jewel sockets. A unique_jewel granted_node requires the unique jewel listed here.'),
                'resistances' => $schema->object([
                    'fire' => $schema->integer(),
                    'cold' => $schema->integer(),
                    'lightning' => $schema->integer(),
                    'chaos' => $schema->integer(),
                ]),
                'content_tier' => $schema->string()->enum(['campaign', 'early_endgame', 'endgame', 'pinnacle']),
            ])->description('The structured build, same shape as validate_build input.')->required(),
        ];
    }
}
