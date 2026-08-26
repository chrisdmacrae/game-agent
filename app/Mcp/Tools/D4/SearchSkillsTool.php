<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Queries\SkillQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchSkillsTool extends Tool
{
    protected string $name = 'search_skills';

    protected string $description = 'Search Diablo IV class skills by name or description text, and filter by class, skill-tree category (basic, core, defensive, ultimate, mastery, conjuration, ...) or game tag. Returns summaries; use get_skill for the full skill with its enhancements/upgrades. Each summary carries the raw `description` and a `description_rendered` with its formula tokens evaluated at rank 1; get_skill renders any rank. Tokens still standing in the rendered text are not computable from the data.';

    public function handle(Request $request, SkillQuery $skills): Response
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:100',
            'class' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:50',
            'tag' => 'nullable|string|max:50',
            'include_unreleased' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        return Response::json($skills->search(
            term: $validated['query'] ?? null,
            className: $validated['class'] ?? null,
            category: $validated['category'] ?? null,
            tag: $validated['tag'] ?? null,
            includeUnreleased: $validated['include_unreleased'] ?? false,
            limit: $validated['limit'] ?? 25,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to match against skill names and descriptions, e.g. "whirlwind", "bleeding".'),
            'class' => $schema->string()->description('Class name, e.g. "Barbarian", "Sorcerer", "Necromancer", "Rogue", "Druid", "Spiritborn".'),
            'category' => $schema->string()->description('Skill tree category, e.g. "basic", "core", "defensive", "ultimate", "mastery".'),
            'tag' => $schema->string()->description('Exact game tag the skill must carry, e.g. "Skill_Channeled", "Skill_Martial".'),
            'include_unreleased' => $schema->boolean()->description('Include datamined skills that are not live yet (default false).'),
            'limit' => $schema->integer()->description('Max results (default 25, max 100).'),
        ];
    }
}
