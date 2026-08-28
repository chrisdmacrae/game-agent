<?php

namespace App\Domain\Poe2\Ggg\Exceptions;

use RuntimeException;

/**
 * A call to the GGG API or OAuth endpoints came back with a status we cannot
 * use. Carries a message safe to show a user or hand back through MCP.
 */
class GggRequestFailed extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message);
    }
}
