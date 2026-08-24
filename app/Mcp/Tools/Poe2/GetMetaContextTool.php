<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Queries\MetaQuery;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetMetaContextTool extends Tool
{
    protected string $name = 'get_meta_context';

    protected string $description = 'Get the current Path of Exile 2 patch/league context and data freshness. Call this FIRST in a session to know which game version the data reflects.';

    public function handle(Request $request, MetaQuery $meta): Response
    {
        return Response::json($meta->context());
    }
}
