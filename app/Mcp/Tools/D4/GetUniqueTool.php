<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Queries\UniqueQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetUniqueTool extends Tool
{
    protected string $name = 'get_unique';

    protected string $description = 'Get one Diablo IV unique item by exact name (or sno_id) with its unique power text and every forced affix, including affixes whose text could not be resolved (those come back with a null text rather than being dropped).';

    public function handle(Request $request, UniqueQuery $uniques): Response
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'sno_id' => 'nullable|integer|min:1',
        ]);

        if (($validated['name'] ?? null) === null && ($validated['sno_id'] ?? null) === null) {
            return Response::error('Pass either name or sno_id.');
        }

        $detail = $uniques->detail($validated['name'] ?? null, $validated['sno_id'] ?? null);

        if ($detail === null) {
            $wanted = $validated['name'] ?? $validated['sno_id'];

            return Response::error("No unique item \"{$wanted}\" found. Use search_uniques to find the right name.");
        }

        return Response::json($detail);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Exact unique item name, e.g. "Ancients\' Oath".'),
            'sno_id' => $schema->integer()->description('The item\'s datamined sno_id, as returned by search_uniques. Takes precedence over name.'),
        ];
    }
}
