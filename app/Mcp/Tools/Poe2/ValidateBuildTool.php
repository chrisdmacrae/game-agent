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
                    'supports' => $schema->array()->items($schema->string())->description('Support gem names socketed into this skill.'),
                ]),
            )->description('All skill setups in the build.')->required(),
            'spirit_available' => $schema->integer()->description('Total spirit available (campaign base is 100; gear/tree can add more).'),
            'passives' => $schema->object([
                'keystones' => $schema->array()->items($schema->string())->description('Keystone names taken.'),
                'notables' => $schema->array()->items($schema->string())->description('Notable names taken.'),
                'points_used' => $schema->integer()->description('Total passive points spent.'),
            ]),
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
