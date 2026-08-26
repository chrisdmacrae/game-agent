<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Queries\ParagonQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchGlyphsTool extends Tool
{
    protected string $name = 'search_glyphs';

    protected string $description = 'Search Diablo IV paragon glyphs by name or effect text, optionally scoped to a class. Each result carries its effect entries, including the attribute conversions a glyph applies to nodes in its radius.';

    public function handle(Request $request, ParagonQuery $paragon): Response
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:100',
            'class' => 'nullable|string|max:50',
            'include_unreleased' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        return Response::json($paragon->searchGlyphs(
            term: $validated['query'] ?? null,
            className: $validated['class'] ?? null,
            includeUnreleased: $validated['include_unreleased'] ?? false,
            limit: $validated['limit'] ?? 25,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to match against glyph names and effect text, e.g. "Intelligence", "Enchanter".'),
            'class' => $schema->string()->description('Class name, e.g. "Sorcerer". Generic glyphs have no class.'),
            'include_unreleased' => $schema->boolean()->description('Include glyphs that are datamined but not live yet (default false).'),
            'limit' => $schema->integer()->description('Max results (default 25, max 100).'),
        ];
    }
}
