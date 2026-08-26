<?php

namespace App\Mcp\Tools\D4;

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

    protected string $description = 'Search the Diablo IV game model documents for a mechanic question (e.g. "how do damage buckets stack", "what does masterworking do", "how do glyphs scale"). Returns matching documents with snippets; read the full document with get_game_model.';

    public function handle(Request $request, ModelDocRepository $docs): Response
    {
        $validated = $request->validate([
            'query' => 'required|string|max:200',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        return Response::json(
            $docs->search('diablo-4', $validated['query'], $validated['limit'] ?? 5)->all(),
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
