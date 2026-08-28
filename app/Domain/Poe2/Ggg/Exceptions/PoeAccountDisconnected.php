<?php

namespace App\Domain\Poe2\Ggg\Exceptions;

use RuntimeException;

/**
 * The stored GGG tokens no longer work — the refresh token expired (90 days)
 * or the user revoked access on pathofexile.com. The link row has been
 * removed; the user has to connect again in settings.
 */
class PoeAccountDisconnected extends RuntimeException
{
    public function __construct(string $message = 'The linked Path of Exile account is no longer authorised. Connect it again in account settings.')
    {
        parent::__construct($message);
    }
}
