<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Queries\PassiveQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetAscendancyTool extends Tool
{
    protected string $name = 'get_ascendancy';

    protected string $description = 'Get an ascendancy class by name (e.g. "Infernalist", "Deadeye", "Titan") with all of its ascendancy passive nodes and their stats.';

    public function handle(Request $request, PassiveQuery $passives): Response
    {
        $validated = $request->validate(['name' => 'required|string|max:50']);

        $detail = $passives->ascendancy($validated['name']);

        if ($detail === null) {
            return Response::error("No ascendancy named \"{$validated['name']}\" found. Use list_classes to see available ascendancies.");
        }

        return Response::json($detail);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Ascendancy name, e.g. "Infernalist".')->required(),
        ];
    }
}
