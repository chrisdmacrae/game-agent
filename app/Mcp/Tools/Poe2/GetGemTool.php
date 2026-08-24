<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Queries\GemQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetGemTool extends Tool
{
    protected string $name = 'get_gem';

    protected string $description = 'Get full details for one gem by exact name: description, skill types, weapon restrictions, costs and spirit reservation, stat values at a given gem level, support socket constraints, and recommended supports.';

    public function handle(Request $request, GemQuery $gems): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'level' => 'nullable|integer|min:1|max:40',
        ]);

        $detail = $gems->detail($validated['name'], $validated['level'] ?? null);

        if ($detail === null) {
            return Response::error("No gem named \"{$validated['name']}\" found. Use search_gems to find the right name.");
        }

        return Response::json($detail);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Exact gem name, e.g. "Spark" or "Martial Tempo".')->required(),
            'level' => $schema->integer()->description('Gem level for stat values (defaults to the highest available level).'),
        ];
    }
}
