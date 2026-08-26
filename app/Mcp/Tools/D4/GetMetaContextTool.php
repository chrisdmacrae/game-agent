<?php

namespace App\Mcp\Tools\D4;

use App\Domain\D4\Queries\MetaQuery;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetMetaContextTool extends Tool
{
    protected string $name = 'get_meta_context';

    protected string $description = 'Get the current Diablo IV patch context and data freshness: game version, import time, released classes, dataset counts, and the standing caveats (no economy data, raw formula tokens in text). Call this FIRST in a session.';

    public function handle(Request $request, MetaQuery $meta): Response
    {
        return Response::json($meta->context());
    }
}
