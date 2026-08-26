<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Queries\UniqueQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchUniquesTool extends Tool
{
    protected string $name = 'search_uniques';

    protected string $description = 'Search Diablo IV unique and mythic unique items by name or unique-power text, filtered by class, item type, and whether the item is mythic. Returns summaries with the unique power text; use get_unique for the full forced-affix list.';

    public function handle(Request $request, UniqueQuery $uniques): Response
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:100',
            'class' => 'nullable|string|max:50',
            'item_type' => 'nullable|string|max:50',
            'mythic' => 'nullable|boolean',
            'include_unreleased' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        return Response::json($uniques->search(
            term: $validated['query'] ?? null,
            className: $validated['class'] ?? null,
            itemType: $validated['item_type'] ?? null,
            isMythic: $validated['mythic'] ?? null,
            includeUnreleased: $validated['include_unreleased'] ?? false,
            limit: $validated['limit'] ?? 25,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to match against unique names and their power text.'),
            'class' => $schema->string()->description('Class the unique is restricted to, e.g. "Barbarian". Class-agnostic uniques have no class and are excluded when this is set.'),
            'item_type' => $schema->string()->description('Item type substring as stored on the item, e.g. "Axe2H", "Helm", "Ring", "Boots".'),
            'mythic' => $schema->boolean()->description('True for mythic uniques only, false to exclude them.'),
            'include_unreleased' => $schema->boolean()->description('Include uniques that are datamined but not live yet (default false).'),
            'limit' => $schema->integer()->description('Max results (default 25, max 100).'),
        ];
    }
}
