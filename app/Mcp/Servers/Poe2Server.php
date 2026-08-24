<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\CraftBuildPrompt;
use App\Mcp\Tools\Poe2\GetAscendancyTool;
use App\Mcp\Tools\Poe2\GetGameModelTool;
use App\Mcp\Tools\Poe2\GetGemTool;
use App\Mcp\Tools\Poe2\GetMetaContextTool;
use App\Mcp\Tools\Poe2\GetPricesTool;
use App\Mcp\Tools\Poe2\GetSupportsForGemTool;
use App\Mcp\Tools\Poe2\GetUniqueTool;
use App\Mcp\Tools\Poe2\ListClassesTool;
use App\Mcp\Tools\Poe2\ListGameModelsTool;
use App\Mcp\Tools\Poe2\SearchGameKnowledgeTool;
use App\Mcp\Tools\Poe2\SearchGemsTool;
use App\Mcp\Tools\Poe2\SearchModsTool;
use App\Mcp\Tools\Poe2\SearchPassivesTool;
use App\Mcp\Tools\Poe2\SearchUniquesTool;
use App\Mcp\Tools\Poe2\ValidateBuildTool;
use Laravel\Mcp\Server;

class Poe2Server extends Server
{
    protected string $name = 'PoE2 Theorycrafter';

    protected string $version = '0.1.0';

    protected string $instructions = <<<'MARKDOWN'
    Theorycrafting toolkit for Path of Exile 2 (Early Access). It provides:

    - **Game data tools** (gems, supports, uniques, affixes, passives, classes) backed by
      datamined game data for the current patch — use them for every factual claim.
    - **Game model documents** (list_game_models / get_game_model) explaining how PoE2's
      mechanics actually work: modifier math, gem linking, spirit, defenses, build anatomy.
      Read the relevant models before reasoning about mechanics — PoE1 intuitions and
      pre-release information are often wrong.
    - **validate_build** to check a draft build against hard game rules. Always validate
      before presenting a build.

    Never invent numeric values: if a number is not in a tool response, say you don't know.
    Start with get_meta_context to see which game version the data reflects.
    MARKDOWN;

    protected array $tools = [
        GetMetaContextTool::class,
        ListGameModelsTool::class,
        GetGameModelTool::class,
        SearchGameKnowledgeTool::class,
        ListClassesTool::class,
        GetAscendancyTool::class,
        SearchPassivesTool::class,
        SearchGemsTool::class,
        GetGemTool::class,
        GetSupportsForGemTool::class,
        SearchUniquesTool::class,
        GetUniqueTool::class,
        SearchModsTool::class,
        ValidateBuildTool::class,
        GetPricesTool::class,
    ];

    protected array $prompts = [
        CraftBuildPrompt::class,
    ];
}
