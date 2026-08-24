<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Poe2Context;
use App\Domain\Poe2\Validation\BuildRules;
use App\Domain\Poe2\Validation\BuildValidator;
use App\Models\SavedBuild;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent(false)]
class SaveBuildTool extends Tool
{
    protected string $name = 'save_build';

    protected string $description = 'Save a finished build and get a permanent shareable web page URL for it. Give the returned URL to the user — the page shows the build (skills, supports, passives, defenses) plus your guide text. Validate the build first with validate_build and fix violations; the validation result is stored and displayed on the page. Include a thorough guide_markdown: it is the main content readers see.';

    public function handle(Request $request, Poe2Context $context, BuildValidator $validator): Response
    {
        $validated = $request->validate(array_merge([
            'name' => 'required|string|max:120',
            'summary' => 'nullable|string|max:500',
            'guide_markdown' => 'nullable|string|max:30000',
        ], BuildRules::rules('build.')));

        $version = $context->version();

        $build = SavedBuild::create([
            'game_id' => $version->game_id,
            'game_version_id' => $version->id,
            'name' => $validated['name'],
            'summary' => $validated['summary'] ?? null,
            'guide_markdown' => $validated['guide_markdown'] ?? null,
            'build' => $validated['build'],
            'validation' => $validator->validate($validated['build']),
        ]);

        return Response::json([
            'id' => $build->public_id,
            'url' => $build->url(),
            'validation' => $build->validation,
            'note' => 'Share the url with the user. The build was validated automatically; if violations are listed, fix them and save again (saving again creates a new page).',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
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
                    'points_used' => $schema->integer(),
                ]),
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
