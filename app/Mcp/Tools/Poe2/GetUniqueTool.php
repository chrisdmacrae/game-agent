<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Queries\UniqueQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetUniqueTool extends Tool
{
    protected string $name = 'get_unique';

    protected string $description = 'Get one unique item by exact name, including all modifier lines (with value ranges), variants, and which lines apply to the current game version.';

    public function handle(Request $request, UniqueQuery $uniques): Response
    {
        $validated = $request->validate(['name' => 'required|string|max:100']);

        $detail = $uniques->detail($validated['name']);

        if ($detail === null) {
            return Response::error("No unique item named \"{$validated['name']}\" found. Use search_uniques to find the right name.");
        }

        return Response::json($detail);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Exact unique item name, e.g. "Astramentis".')->required(),
        ];
    }
}
