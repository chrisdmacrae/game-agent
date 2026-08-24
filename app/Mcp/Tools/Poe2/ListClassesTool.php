<?php

namespace App\Mcp\Tools\Poe2;

use App\Domain\Poe2\Queries\PassiveQuery;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListClassesTool extends Tool
{
    protected string $name = 'list_classes';

    protected string $description = 'List all Path of Exile 2 character classes with their base stats and available ascendancies.';

    public function handle(Request $request, PassiveQuery $passives): Response
    {
        return Response::json($passives->listClasses());
    }
}
