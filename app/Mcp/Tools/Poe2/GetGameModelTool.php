<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Games\ModelDocRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetGameModelTool extends Tool
{
    protected string $name = 'get_game_model';

    protected string $description = 'Read one game model document in full by id (from list_game_models).';

    public function handle(Request $request, ModelDocRepository $docs): Response
    {
        $validated = $request->validate(['id' => 'required|string|max:100']);

        $doc = $docs->find('poe2', $validated['id']);

        if ($doc === null) {
            return Response::error("No game model \"{$validated['id']}\". Use list_game_models for available ids.");
        }

        return Response::text("# {$doc['title']}\n\n{$doc['body']}");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Document id, e.g. "modifier-algebra", "spirit", "build-anatomy".')->required(),
        ];
    }
}
