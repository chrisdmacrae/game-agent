<?php

namespace App\Mcp\Prompts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;

class CraftBuildPrompt extends Prompt
{
    protected string $name = 'craft_build';

    protected string $description = 'Start a guided theorycrafting session: craft a Path of Exile 2 build for a goal (league start, bossing, mapping, hardcore...) using the toolkit correctly.';

    public function handle(Request $request): Response
    {
        $goal = $request->get('goal') ?? 'a well-rounded league starter';

        return Response::text(<<<MARKDOWN
        You are theorycrafting a Path of Exile 2 build for this goal: **{$goal}**.

        Follow this workflow strictly:

        1. Call `get_meta_context` to learn the current patch and data freshness.
        2. Call `list_game_models`, then read the models relevant to your build decisions
           (at minimum `build-anatomy`, `gems-and-links`, and `spirit`). These encode the
           game's rules — do not rely on memory of PoE1 or outdated PoE2 patches.
        3. Choose class/ascendancy with `list_classes` and `get_ascendancy`.
        4. Find skills and supports with `search_gems`, `get_gem`, and
           `get_supports_for_gem`. Check spirit costs of persistent effects.
        5. Ground itemization in `search_uniques`, `get_unique`, and `search_mods`
           (what can actually roll on each slot), and key passives in `search_passives`.
           Then compute the tree with `plan_tree_path` — give it your class and target
           notables/keystones and it returns legal, contiguous node_ids. Never
           hand-pick node ids: the game requires sequential pathing.
        6. Assemble the build, then call `validate_build`. Fix every violation and
           re-validate until clean. Take warnings seriously.
        7. When the user is happy with the build, offer to save it with `save_build`
           (include a full guide_markdown writeup) and share the returned URL — it is
           a permanent public page for the build.

        Hard rules to respect:
        - NEVER invent numbers (damage values, spirit costs, mod ranges). Every number
          you present must come from a tool response.
        - Only ONE copy of each support gem is allowed across the entire build.
        - Present the final build with: class/ascendancy, skill setups with supports,
          spirit budget, key passives, gear priorities per slot, and leveling notes.
        MARKDOWN);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'goal' => $schema->string()->description('What the build should achieve, e.g. "budget bossing Witch", "tanky league starter".'),
        ];
    }
}
