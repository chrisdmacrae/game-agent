<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Queries\PriceQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetPricesTool extends Tool
{
    protected string $name = 'get_prices';

    protected string $description = 'Get current PoE2 currency exchange rates (in Divine Orbs) for the active trade league, from poe.ninja. Use to ground budget estimates (e.g. what an Exalted or Chaos Orb is worth). Unique item prices are not yet available.';

    public function handle(Request $request, PriceQuery $prices): Response
    {
        $validated = $request->validate([
            'term' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:60',
        ]);

        $result = $prices->currency($validated['term'] ?? null, $validated['limit'] ?? 30);

        if ($result['prices'] === [] && $result['conversion_rates'] === []) {
            return Response::error('No price data imported yet (or no match for the term). Price data updates hourly when the scheduler runs.');
        }

        return Response::json($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'term' => $schema->string()->description('Optional currency name filter, e.g. "exalted", "essence".'),
            'limit' => $schema->integer()->description('Max rows (default 30).'),
        ];
    }
}
