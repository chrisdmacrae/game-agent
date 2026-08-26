<?php

namespace App\Mcp\Tools\D4;

use App\Domain\Games\ModelDocRepository;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListGameModelsTool extends Tool
{
    protected string $name = 'list_game_models';

    protected string $description = 'List the curated "game model" documents explaining how Diablo IV actually works (additive damage buckets, paragon boards and glyphs, tempering and masterworking, itemization, build anatomy). Read these BEFORE reasoning about build mechanics — they encode the rules the datamined game data alone does not state.';

    public function handle(Request $request, ModelDocRepository $docs): Response
    {
        return Response::json(
            $docs->all('diablo-4')
                ->map(fn (array $doc) => [
                    'id' => $doc['id'],
                    'title' => $doc['title'],
                    'summary' => $doc['summary'],
                ])
                ->all(),
        );
    }
}
