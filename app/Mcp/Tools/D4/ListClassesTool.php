<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Queries\SkillQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListClassesTool extends Tool
{
    protected string $name = 'list_classes';

    protected string $description = 'List the Diablo IV character classes with their primary resource (fury, essence, spirit, mana, ...), class description, and how many skills each has. Released classes only unless include_unreleased is set.';

    public function handle(Request $request, SkillQuery $skills): Response
    {
        $validated = $request->validate(['include_unreleased' => 'nullable|boolean']);

        return Response::json($skills->listClasses(
            includeUnreleased: $validated['include_unreleased'] ?? false,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'include_unreleased' => $schema->boolean()->description('Include classes that are datamined but not live yet (default false).'),
        ];
    }
}
