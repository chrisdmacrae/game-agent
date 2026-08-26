<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Calc\ComputedStats;
use App\Domain\D4\D4BuildPayload;
use App\Domain\D4\Validation\D4BuildRules;
use App\Domain\D4\Validation\D4BuildValidator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ValidateBuildTool extends Tool
{
    protected string $name = 'validate_build';

    protected string $description = 'Validate a draft Diablo IV build against the game\'s hard rules without saving it: skill existence and class ownership, the six action bar slots, paragon boards and glyphs existing and belonging to the class (including path connectivity when paragon[].nodes are given), aspects existing and the one-copy-per-aspect rule, unique item names, tempering recipes, and resistance caps. ALWAYS run this before presenting a build to the user, and re-run after changes. Returns violations (illegal), warnings (probably wrong), suggestions, and the server-computed baseline stats (`computed`, with the assumptions they rest on) — quote those instead of inventing dps/ehp.';

    public function handle(Request $request, D4BuildValidator $validator): Response
    {
        $validated = $request->validate(D4BuildRules::rules());

        $definition = ComputedStats::apply(D4BuildPayload::normalize($validated));

        return Response::json(
            $validator->validate($definition) + ['computed' => $definition['computed'] ?? null],
        );
    }

    /**
     * The LLM-facing mirror of D4BuildRules; shared with save_build so the two
     * tools describe exactly the same payload.
     */
    public function schema(JsonSchema $schema): array
    {
        return D4BuildSchema::properties($schema);
    }
}
