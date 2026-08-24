<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Games\ModelDocRepository;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListGameModelsTool extends Tool
{
    protected string $name = 'list_game_models';

    protected string $description = 'List the curated "game model" documents explaining how Path of Exile 2 actually works (modifier math, gem linking rules, spirit budgeting, defenses, build anatomy...). Read these BEFORE reasoning about build mechanics — they encode the rules the game data alone does not state.';

    public function handle(Request $request, ModelDocRepository $docs): Response
    {
        return Response::json(
            $docs->all('poe2')
                ->map(fn (array $doc) => [
                    'id' => $doc['id'],
                    'title' => $doc['title'],
                    'summary' => $doc['summary'],
                ])
                ->all(),
        );
    }
}
