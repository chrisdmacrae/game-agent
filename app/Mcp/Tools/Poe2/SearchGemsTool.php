<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Queries\GemQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchGemsTool extends Tool
{
    protected string $name = 'search_gems';

    protected string $description = 'Search Path of Exile 2 skill and support gems by name/description text, gem type, and tags. Returns compact summaries; use get_gem for full per-level details.';

    public function handle(Request $request, GemQuery $gems): Response
    {
        $validated = $request->validate([
            'term' => 'nullable|string|max:100',
            'gem_type' => 'nullable|string|in:active,support',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        return Response::json($gems->search(
            term: $validated['term'] ?? null,
            gemType: $validated['gem_type'] ?? null,
            tags: $validated['tags'] ?? [],
            limit: $validated['limit'] ?? 20,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'term' => $schema->string()->description('Text to match against gem names and descriptions (e.g. "lightning", "totem").'),
            'gem_type' => $schema->string()->enum(['active', 'support'])->description('Filter to active skill gems or support gems.'),
            'tags' => $schema->array()->items($schema->string())->description('Gem tags that must all be present (e.g. ["spell", "cold"], ["minion"], ["attack", "projectile"]).'),
            'limit' => $schema->integer()->description('Max results (default 20, max 50).'),
        ];
    }
}
