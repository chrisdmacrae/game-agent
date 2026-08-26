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

    protected string $description = 'Get one Diablo IV skill by exact name (or sno_id): class, category, max rank, tags, description, and every enhancement/upgrade node with its own text. Text is raw game text with unevaluated tokens ({c_number}, {SF_12}, {payload:...}) — report it as written and never invent the numbers behind those tokens.';

    public function handle(Request $request, SkillQuery $skills): Response
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'sno_id' => 'nullable|integer|min:1',
        ]);

        if (($validated['name'] ?? null) === null && ($validated['sno_id'] ?? null) === null) {
            return Response::error('Pass either name or sno_id.');
        }

        $detail = $skills->detail($validated['name'] ?? null, $validated['sno_id'] ?? null);

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
        ];
    }
}
