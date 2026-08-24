<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Games\ModelDocRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchGameKnowledgeTool extends Tool
{
    protected string $name = 'search_game_knowledge';

    protected string $description = 'Search the game model documents for a mechanic question (e.g. "how does spirit reservation work", "increased vs more damage"). Returns matching documents with snippets; read the full document with get_game_model.';

    public function handle(Request $request, ModelDocRepository $docs): Response
    {
        $validated = $request->validate([
            'query' => 'required|string|max:200',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        return Response::json(
            $docs->search('poe2', $validated['query'], $validated['limit'] ?? 5)->all(),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Mechanic question or keywords.')->required(),
            'limit' => $schema->integer()->description('Max documents (default 5).'),
        ];
    }
}
