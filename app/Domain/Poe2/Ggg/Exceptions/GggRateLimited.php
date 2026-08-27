<?php

namespace App\Domain\Poe2\Ggg\Exceptions;

use RuntimeException;

/**
 * GGG applies dynamic, per-account and per-client rate limits and answers with
 * 429 plus a Retry-After. We never retry blindly: the caller is told how long
 * to wait so the client (or the user) can decide.
 */
class GggRateLimited extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct(
            "The Path of Exile API is rate limiting this account. Try again in {$retryAfterSeconds} seconds.",
        );
    }
}
