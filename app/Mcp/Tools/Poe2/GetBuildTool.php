<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\PobExporter;
use App\Models\Build;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetBuildTool extends Tool
{
    protected string $name = 'get_build';

    protected string $description = 'Load a previously saved build by its id (from a save_build response or a build page URL). Use this to review or iterate on an existing build — then save_build the improved version as a new page.';

    public function handle(Request $request, PobExporter $exporter): Response
    {
        $validated = $request->validate(['id' => 'required|string|max:32']);

        // Drafts are readable by their owner only.
        $build = Build::query()
            ->visibleTo($request->user())
            ->where('public_id', $validated['id'])
            ->first();

        if ($build === null) {
            return Response::error("No saved build with id \"{$validated['id']}\".");
        }

        return Response::json([
            'id' => $build->public_id,
            'url' => $build->url(),
            'visibility' => $build->visibility,
            'name' => $build->name,
            'summary' => $build->summary,
            'guide_markdown' => $build->guide_markdown,
            'build' => $build->build,
            'validation' => $build->validation,
            'game_version' => $build->gameVersion?->version,
            'pob_code' => $exporter->code($build),
            'created_at' => $build->created_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The build\'s public id, e.g. "k3v9x2m8q1zr".')->required(),
        ];
    }
}
