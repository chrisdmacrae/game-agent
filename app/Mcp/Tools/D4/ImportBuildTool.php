<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Import\MaxrollPlanner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;

#[IsReadOnly]
class ImportBuildTool extends Tool
{
    protected string $name = 'import_build';

    protected string $description = 'Import a Maxroll Diablo IV planner into a draft build payload. Give it a planner URL (https://maxroll.gg/d4/planner/xxxxxxxx) or the bare planner id. It fetches the planner server-side, maps the chosen variant onto this app\'s build shape as far as the imported game data allows, and returns {payload, unmapped, source_url} WITHOUT saving anything. Review the payload, fill in what `unmapped` says could not be resolved, run validate_build, then call save_build. A planner usually holds several variants (Starter, Midgame, Endgame, Speedfarm); the response lists them and you can re-run with `variant` to pick another. Always credit the planner: pass the returned source_url on to the user.';

    /**
     * Hidden entirely unless the import is switched on, the way save_build is
     * hidden from anonymous clients: a tool a client can see is a tool it will
     * try, and this one reaches a third-party site.
     */
    public function shouldRegister(): bool
    {
        return (bool) config('games.diablo-4.maxroll_import_enabled');
    }

    public function handle(Request $request, MaxrollPlanner $planner): Response
    {
        if (! $planner->enabled()) {
            return Response::error('Maxroll planner import is disabled on this deployment (set D4_MAXROLL_IMPORT_ENABLED=true to switch it on). Ask the user to paste the build details instead, or rebuild it from the game data tools.');
        }

        $validated = $request->validate([
            'planner' => 'required|string|max:300',
            'variant' => 'nullable|integer|min:0|max:50',
        ]);

        $id = $planner->parseId($validated['planner']);

        if ($id === null) {
            return Response::error("\"{$validated['planner']}\" is not a Maxroll planner URL or id. Expected something like https://maxroll.gg/d4/planner/rf5dmg0x or just rf5dmg0x.");
        }

        try {
            $mapped = $planner->map($planner->fetch($id), $validated['variant'] ?? null);
        } catch (RuntimeException $exception) {
            return Response::error("Could not import planner \"{$id}\": {$exception->getMessage()}");
        }

        return Response::json([
            'source' => 'Maxroll planner',
            'source_url' => $planner->sourceUrl($id),
            'planner_id' => $id,
            'variant' => $mapped['variant'],
            'variants' => $mapped['variants'],
            'payload' => $mapped['payload'],
            'unmapped' => $mapped['unmapped'],
            'note' => 'Nothing has been saved. This is a best-effort mapping of someone else\'s planner: skill ranks, masterworking levels and seasonal powers are not in the planner data, and anything listed under `unmapped` still needs a name from the game data tools. Review it with the user, run validate_build, then save_build — and credit source_url.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'planner' => $schema->string()->description('A Maxroll D4 planner URL (https://maxroll.gg/d4/planner/rf5dmg0x) or the bare planner id ("rf5dmg0x").')->required(),
            'variant' => $schema->integer()->description('Which variant of the planner to map, as a zero-based index into the `variants` list a previous call returned. Defaults to the first.'),
        ];
    }
}
