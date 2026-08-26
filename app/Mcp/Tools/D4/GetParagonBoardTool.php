<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Queries\ParagonQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetParagonBoardTool extends Tool
{
    protected string $name = 'get_paragon_board';

    protected string $description = 'Diablo IV paragon boards. Called WITHOUT a name it lists the available boards (optionally for one class) with their node/socket/gate counts — call it that way first. Called WITH a name it returns that board plus its full node grid: a row-major 2D array where null is empty space and a filled cell is a node with key, name, rarity, attributes, has_socket and is_gate.';

    public function handle(Request $request, ParagonQuery $paragon): Response
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'class' => 'nullable|string|max:50',
            'include_unreleased' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if (($validated['name'] ?? null) === null) {
            return Response::json([
                'boards' => $paragon->listBoards(
                    className: $validated['class'] ?? null,
                    includeUnreleased: $validated['include_unreleased'] ?? false,
                    limit: $validated['limit'] ?? 50,
                ),
                'note' => 'Call get_paragon_board again with one of these names to get its full node grid.',
            ]);
        }

        $board = $paragon->board($validated['name'], $validated['class'] ?? null);

        if ($board === null) {
            return Response::error("No paragon board named \"{$validated['name']}\" found. Call get_paragon_board without a name to list the boards.");
        }

        return Response::json($board);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Exact board name. Omit to list boards instead of returning one grid.'),
            'class' => $schema->string()->description('Class name to scope to, e.g. "Barbarian". Generic boards have no class.'),
            'include_unreleased' => $schema->boolean()->description('Include boards that are datamined but not live yet (default false).'),
            'limit' => $schema->integer()->description('Max boards when listing (default 50).'),
        ];
    }
}
