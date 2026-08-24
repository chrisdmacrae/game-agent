<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Queries\UniqueQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchUniquesTool extends Tool
{
    protected string $name = 'search_uniques';

    protected string $description = 'Search Path of Exile 2 unique items by name, base item, item class (e.g. "Amulet", "Body Armour", "Two Hand Mace"), or by text appearing in their modifiers.';

    public function handle(Request $request, UniqueQuery $uniques): Response
    {
        $validated = $request->validate([
            'term' => 'nullable|string|max:100',
            'item_class' => 'nullable|string|max:50',
            'mod_text' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        return Response::json($uniques->search(
            term: $validated['term'] ?? null,
            itemClass: $validated['item_class'] ?? null,
            modText: $validated['mod_text'] ?? null,
            limit: $validated['limit'] ?? 20,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'term' => $schema->string()->description('Text to match against unique or base item names.'),
            'item_class' => $schema->string()->description('Item class filter, e.g. "Amulet", "Ring", "Body Armour", "Wand".'),
            'mod_text' => $schema->string()->description('Text that must appear in the item\'s modifiers, e.g. "minion", "spirit".'),
            'limit' => $schema->integer()->description('Max results (default 20).'),
        ];
    }
}
