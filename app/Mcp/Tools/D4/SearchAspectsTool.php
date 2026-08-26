<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Queries\AspectQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchAspectsTool extends Tool
{
    protected string $name = 'search_aspects';

    protected string $description = 'Search Diablo IV legendary aspects (the codex powers you imprint on gear) by name or power text, and filter by category (offensive, defensive, resource, utility, mobility), by the class the aspect belongs to, or by an item type it can be imprinted on. This is the tool to answer "which aspects interact with skill X" — search the skill name as the query.';

    public function handle(Request $request, AspectQuery $aspects): Response
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'class' => 'nullable|string|max:50',
            'item_type' => 'nullable|string|max:50',
            'include_unreleased' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        return Response::json($aspects->search(
            term: $validated['query'] ?? null,
            category: $validated['category'] ?? null,
            className: $validated['class'] ?? null,
            itemType: $validated['item_type'] ?? null,
            includeUnreleased: $validated['include_unreleased'] ?? false,
            limit: $validated['limit'] ?? 25,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to match against aspect names and power text, e.g. "Whirlwind", "Dust Devil", "barrier".'),
            'category' => $schema->string()->description('Aspect category: "offensive", "defensive", "resource", "utility" or "mobility".'),
            'class' => $schema->string()->description('Class the aspect is restricted to, e.g. "Barbarian". Generic aspects have no class and are excluded when this is set.'),
            'item_type' => $schema->string()->description('Item type the aspect can be imprinted on, exactly as listed on the aspect, e.g. "Amulet", "Two-Handed Axe", "Gloves".'),
            'include_unreleased' => $schema->boolean()->description('Include aspects that are datamined but not live yet (default false).'),
            'limit' => $schema->integer()->description('Max results (default 25, max 100).'),
        ];
    }
}
