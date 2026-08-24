<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Queries\GemQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetSupportsForGemTool extends Tool
{
    protected string $name = 'get_supports_for_gem';

    protected string $description = 'List support gems compatible with a given active skill gem, based on the support\'s allowed/excluded skill types. Recommended supports (from game data) are flagged and sorted first. Remember: a build may only use ONE copy of each support gem across all skills.';

    public function handle(Request $request, GemQuery $gems): Response
    {
        $validated = $request->validate([
            'gem' => 'required|string|max:100',
            'term' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $gems->supportsFor(
            $validated['gem'],
            $validated['term'] ?? null,
            $validated['limit'] ?? 30,
        );

        if ($result === null) {
            return Response::error("No active gem named \"{$validated['gem']}\" found. Use search_gems to find the right name.");
        }

        return Response::json($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'gem' => $schema->string()->description('Exact active skill gem name, e.g. "Spark".')->required(),
            'term' => $schema->string()->description('Optional text filter on support names.'),
            'limit' => $schema->integer()->description('Max supports to return (default 30).'),
        ];
    }
}
