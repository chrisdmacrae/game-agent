<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Validation\BuildRules;
use App\Domain\Poe2\Validation\BuildValidator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ValidateBuildTool extends Tool
{
    protected string $name = 'validate_build';

    protected string $description = 'Validate a draft build against PoE2\'s hard rules: gem/support existence, support-to-skill type compatibility, the one-copy-per-support-gem rule, support socket limits, spirit reservation budget, passive node existence, and resistance targets. ALWAYS run this before presenting a build to the user, and re-run after changes. Returns violations (illegal), warnings (probably wrong), and suggestions.';

    public function handle(Request $request, BuildValidator $validator): Response
    {
        $validated = $request->validate(BuildRules::rules());

        return Response::json($validator->validate($validated));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'class' => $schema->string()->description('Character class, e.g. "Witch".'),
            'ascendancy' => $schema->string()->description('Ascendancy, e.g. "Infernalist".'),
            'level' => $schema->integer()->description('Target character level.'),
            'skills' => $schema->array()->items(
                $schema->object([
                    'gem' => $schema->string()->description('Active/spirit skill gem name.')->required(),
                    'supports' => $schema->array()->items($schema->anyOf([
                        $schema->string()->description('Support gem name.'),
                        $schema->object([
                            'name' => $schema->string()->required(),
                            'effect' => $schema->string()->description('What this support does for this skill.'),
                        ]),
                    ]))->description('Support gems socketed into this skill: a name, or {name, effect}.'),
                ]),
            )->description('All skill setups in the build.')->required(),
            'spirit_available' => $schema->integer()->description('Total spirit available (campaign base is 100; gear/tree can add more).'),
            'passives' => $schema->object([
                'keystones' => $schema->array()->items($schema->string())->description('Keystone names taken.'),
                'notables' => $schema->array()->items($schema->string())->description('Notable names taken.'),
                'ascendancy_nodes' => $schema->array()->items($schema->string())->description('Ascendancy passive names taken (must belong to the build\'s ascendancy).'),
                'points_used' => $schema->integer()->description('Total passive points spent.'),
                'import_string' => $schema->string()->description('The passive tree export string from the in-game planner. Only set this if the user pasted one; never invent it.'),
                'node_ids' => $schema->array()->items($schema->integer())->description('Exact allocated passive node ids (from search_passives node_id values). Optional but recommended: enables rendering the allocation on the build page tree.'),
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
                    'runes' => $schema->array()->max(8)->items($schema->string()->nullable())->description('One entry per rune socket, in socket order; null for an empty socket.'),
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
            ])->description('Expected resistance percentages, if known.'),
            'content_tier' => $schema->string()->enum(['campaign', 'early_endgame', 'endgame', 'pinnacle'])->description('Content the build targets (affects defense expectations).'),
        ];
    }
}
