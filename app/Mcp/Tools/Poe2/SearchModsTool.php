<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Queries\ModQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchModsTool extends Tool
{
    protected string $name = 'search_mods';

    protected string $description = 'Search the affix pool: which modifiers can roll on which gear. Answers questions like "what can roll +to maximum Life on boots?" or "can amulets roll +to gem levels?". Filter by item tag (amulet, ring, belt, helmet, gloves, boots, body_armour, shield, focus, wand, sceptre, staff, bow, crossbow, quiver, ...) and generation type (prefix/suffix).';

    public function handle(Request $request, ModQuery $mods): Response
    {
        $validated = $request->validate([
            'term' => 'nullable|string|max:100',
            'item_tag' => 'nullable|string|max:50',
            'generation_type' => 'nullable|string|in:prefix,suffix,implicit,corrupted,unique,enchantment',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        return Response::json($mods->search(
            term: $validated['term'] ?? null,
            itemTag: $validated['item_tag'] ?? null,
            generationType: $validated['generation_type'] ?? null,
            limit: $validated['limit'] ?? 30,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'term' => $schema->string()->description('Text to match in the mod text, e.g. "maximum Life", "gem levels", "Spirit".'),
            'item_tag' => $schema->string()->description('Item tag the mod must be able to spawn on, e.g. "amulet", "boots", "body_armour".'),
            'generation_type' => $schema->string()->enum(['prefix', 'suffix', 'implicit', 'corrupted', 'unique', 'enchantment'])->description('Affix slot type.'),
            'limit' => $schema->integer()->description('Max mod families to return (default 30).'),
        ];
    }
}
