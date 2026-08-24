<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Queries\PassiveQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchPassivesTool extends Tool
{
    protected string $name = 'search_passives';

    protected string $description = 'Search the shared passive skill tree by node name or stat text. Filter by kind: keystone, notable, small, jewel_socket. Use get_ascendancy for ascendancy-specific nodes.';

    public function handle(Request $request, PassiveQuery $passives): Response
    {
        $validated = $request->validate([
            'term' => 'nullable|string|max:100',
            'kind' => 'nullable|string|in:keystone,notable,small,jewel_socket',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        return Response::json($passives->searchNodes(
            term: $validated['term'] ?? null,
            kind: $validated['kind'] ?? null,
            limit: $validated['limit'] ?? 25,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'term' => $schema->string()->description('Text to match against node names or stats, e.g. "minion damage", "block chance".'),
            'kind' => $schema->string()->enum(['keystone', 'notable', 'small', 'jewel_socket'])->description('Node kind filter.'),
            'limit' => $schema->integer()->description('Max results (default 25).'),
        ];
    }
}
