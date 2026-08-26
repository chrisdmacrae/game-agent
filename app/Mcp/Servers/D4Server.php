<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\D4\GetBuildTool;
use App\Mcp\Tools\D4\GetGameModelTool;
use App\Mcp\Tools\D4\GetMetaContextTool;
use App\Mcp\Tools\D4\GetParagonBoardTool;
use App\Mcp\Tools\D4\GetSkillTool;
use App\Mcp\Tools\D4\GetUniqueTool;
use App\Mcp\Tools\D4\ImportBuildTool;
use App\Mcp\Tools\D4\ListClassesTool;
use App\Mcp\Tools\D4\ListGameModelsTool;
use App\Mcp\Tools\D4\SaveBuildTool;
use App\Mcp\Tools\D4\SearchAffixesTool;
use App\Mcp\Tools\D4\SearchAspectsTool;
use App\Mcp\Tools\D4\SearchGameKnowledgeTool;
use App\Mcp\Tools\D4\SearchGlyphsTool;
use App\Mcp\Tools\D4\SearchSkillsTool;
use App\Mcp\Tools\D4\SearchUniquesTool;
use App\Mcp\Tools\D4\ValidateBuildTool;
use Laravel\Mcp\Server;

class D4Server extends Server
{
    protected string $name = 'Diablo 4 Theorycrafter';

    protected string $version = '0.1.0';

    protected string $instructions = <<<'MARKDOWN'
    Theorycrafting toolkit for Diablo IV. It provides:

    - **Game data tools** (classes, skills, paragon boards and glyphs, aspects, uniques,
      affixes and tempering) backed by datamined game data for the current patch — use
      them for every factual claim.
    - **Game model documents** (list_game_models / get_game_model / search_game_knowledge)
      explaining how Diablo IV's mechanics actually work: additive damage buckets,
      paragon boards and glyph radii, tempering, masterworking, itemization and build
      anatomy. Read the relevant models before reasoning about mechanics — Diablo IV
      stacks most modifiers **additively inside buckets**, so Path of Exile style
      multiplicative "more" math gives wrong answers here.
    - **validate_build** to check a draft build against hard game rules, and **save_build**
      (signed-in users only) to publish it as a shareable page. Always validate before
      presenting a build.

    A build here is six equipped skills plus paragon boards with rotations and glyphs,
    gear carrying aspects, tempered affixes, masterworking and two-rune runewords, a
    mercenary pairing and the five resistances. Only `equipped_skills` is required —
    save a partial build as a draft and the user finishes it on the web.

    Start with get_meta_context to see which patch the data reflects. The data carries no
    season name and no economy or trade information, so never state which season is live
    and never price an item.

    Never invent numeric values. Skill, affix, aspect and unique text comes back twice: the
    raw game string (`description`, `text`, `power_text`) with its markup and formula tokens
    intact, and a rendered version (`description_rendered`, `display_text`) with the numbers
    evaluated from the data — a skill's script formulas at a given rank, an affix's roll
    range at its top item-power breakpoint. Prefer the rendered text and quote its numbers.

    A token still standing in a rendered string — `{payload:DAMAGE_TOOLTIP}`,
    `{Resource Cost}`, `{dot:...}`, `{SF_9}`, `[Affix_Value_1*100|%|]` — means that value is
    **not derivable from this data**, not that it is zero. Quote such a line as written, say
    the concrete number is not in the data, and never guess what the token resolves to.
    A `[Mod.UpgradeB: ...]` prefix marks text that only applies when that upgrade is taken.
    MARKDOWN;

    protected array $tools = [
        GetMetaContextTool::class,
        ListGameModelsTool::class,
        GetGameModelTool::class,
        SearchGameKnowledgeTool::class,
        ListClassesTool::class,
        SearchSkillsTool::class,
        GetSkillTool::class,
        GetParagonBoardTool::class,
        SearchGlyphsTool::class,
        SearchAspectsTool::class,
        SearchUniquesTool::class,
        GetUniqueTool::class,
        SearchAffixesTool::class,
        ValidateBuildTool::class,
        ImportBuildTool::class,
        SaveBuildTool::class,
        GetBuildTool::class,
    ];
}
