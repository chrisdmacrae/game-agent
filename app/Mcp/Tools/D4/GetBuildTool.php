<?php

namespace App\Mcp\Tools\D4;

use App\Models\Build;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The D4 twin of the PoE2 get_build. The lookup itself is game-agnostic, but
 * the PoE2 tool also returns a Path of Building export code — which is
 * meaningless for Diablo IV and queries PoE2 tables — so the two stay separate
 * rather than the D4 server registering a tool that advertises PoB codes.
 */
#[IsReadOnly]
class GetBuildTool extends Tool
{
    protected string $name = 'get_build';

    protected string $description = 'Load a previously saved build by its id (from a save_build response or a build page URL). Use this to review or iterate on an existing build — then save_build the improved version, passing the same id to update the page in place.';

    public function handle(Request $request): Response
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
