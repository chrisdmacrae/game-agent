<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\D4\GetGameModelTool;
use App\Mcp\Tools\D4\GetMetaContextTool;
use App\Mcp\Tools\D4\GetParagonBoardTool;
use App\Mcp\Tools\D4\GetSkillTool;
use App\Mcp\Tools\D4\GetUniqueTool;
use App\Mcp\Tools\D4\ListClassesTool;
use App\Mcp\Tools\D4\ListGameModelsTool;
use App\Mcp\Tools\D4\SearchAffixesTool;
use App\Mcp\Tools\D4\SearchAspectsTool;
use App\Mcp\Tools\D4\SearchGameKnowledgeTool;
use App\Mcp\Tools\D4\SearchGlyphsTool;
use App\Mcp\Tools\D4\SearchSkillsTool;
use App\Mcp\Tools\D4\SearchUniquesTool;
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

    Start with get_meta_context to see which patch the data reflects. The data carries no
    season name and no economy or trade information, so never state which season is live
    and never price an item.

    Never invent numeric values. Skill, affix, aspect and unique text is raw game text and
    still contains unevaluated formula tokens — `{c_number}`, `{if:ADVANCED_TOOLTIP}`,
    `{SF_12}`, `{payload:...}`, `[Affix_Value_1|%|]`. Numeric evaluation of those tokens is
    not available yet: quote the text as written, say the concrete value is not in the
    data, and do not guess what a token resolves to.
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
    ];
}
