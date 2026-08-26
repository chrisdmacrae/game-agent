<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Queries\SkillQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetSkillTool extends Tool
{
    protected string $name = 'get_skill';

    protected string $description = 'Get one Diablo IV skill by exact name (or sno_id): class, category, max rank, tags, description, and every enhancement/upgrade node with its own text. Each text comes twice: `description` is the raw game string and `description_rendered` is the same text with its formula tokens evaluated for the requested rank (default rank 1). `rank_values` lists every script formula value at every rank so you can see the scaling. Tokens still standing in the rendered text ({payload:...}, {Resource Cost}, {SF_9}) are the ones that are not computable from the data — quote them as written and never invent what they resolve to.';

    public function handle(Request $request, SkillQuery $skills): Response
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'sno_id' => 'nullable|integer|min:1',
            'rank' => 'nullable|integer|min:1|max:100',
        ]);

        if (($validated['name'] ?? null) === null && ($validated['sno_id'] ?? null) === null) {
            return Response::error('Pass either name or sno_id.');
        }

        $detail = $skills->detail(
            $validated['name'] ?? null,
            $validated['sno_id'] ?? null,
            $validated['rank'] ?? 1,
        );

        if ($detail === null) {
            $wanted = $validated['name'] ?? $validated['sno_id'];

            return Response::error("No skill \"{$wanted}\" found. Use search_skills to find the right name.");
        }

        return Response::json($detail);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Exact skill name, e.g. "Whirlwind".'),
            'sno_id' => $schema->integer()->description('The skill\'s datamined sno_id, as returned by search_skills. Takes precedence over name.'),
            'rank' => $schema->integer()->description('Skill rank to render the text at, 1 to the skill\'s max_rank (default 1). Out-of-range ranks are clamped.'),
        ];
    }
}
