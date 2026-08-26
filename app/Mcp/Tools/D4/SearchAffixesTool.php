<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Queries\AffixQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchAffixesTool extends Tool
{
    protected string $name = 'search_affixes';

    protected string $description = 'Search the Diablo IV affix pool: the stat lines, legendary powers and tempering manual affixes that can appear on gear. Filter by text, by magic_type (stat = ordinary rollable stat, power = legendary power, unique_power = forced onto a unique), by class, by item type, and by is_tempering / temper_family to answer "what can I temper onto this?". Affix text keeps its raw roll tokens (e.g. "+[{VALUE}*100|1%|] Critical Strike Chance") and value_range says whether a literal range could be derived or only a formula name is known.';

    public function handle(Request $request, AffixQuery $affixes): Response
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:100',
            'is_tempering' => 'nullable|boolean',
            'temper_family' => 'nullable|string|max:100',
            'magic_type' => 'nullable|string|in:stat,power,unique_power',
            'class' => 'nullable|string|max:50',
            'item_type' => 'nullable|string|max:50',
            'include_unreleased' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        return Response::json($affixes->search(
            term: $validated['query'] ?? null,
            isTempering: $validated['is_tempering'] ?? null,
            temperFamily: $validated['temper_family'] ?? null,
            magicType: $validated['magic_type'] ?? null,
            className: $validated['class'] ?? null,
            itemType: $validated['item_type'] ?? null,
            includeUnreleased: $validated['include_unreleased'] ?? false,
            limit: $validated['limit'] ?? 30,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to match against the affix text, display name or key, e.g. "Critical Strike Chance", "Attack Speed".'),
            'is_tempering' => $schema->boolean()->description('True for tempering-manual affixes only, false to exclude them.'),
            'temper_family' => $schema->string()->description('Tempering/affix family substring, e.g. "AttackSpeed_Sorc_Tag_Pyromancy", "Crit_Chance".'),
            'magic_type' => $schema->string()->enum(['stat', 'power', 'unique_power'])->description('stat = ordinary rollable stat, power = legendary/aspect power, unique_power = forced onto a unique item.'),
            'class' => $schema->string()->description('Class the affix is restricted to, e.g. "Barbarian". Class-agnostic affixes have no class and are excluded when this is set.'),
            'item_type' => $schema->string()->description('Item type the affix can roll on, exactly as listed on the affix, e.g. "Axe", "Gloves".'),
            'include_unreleased' => $schema->boolean()->description('Include affixes that are datamined but not live yet (default false).'),
            'limit' => $schema->integer()->description('Max results (default 30, max 100).'),
        ];
    }
}
