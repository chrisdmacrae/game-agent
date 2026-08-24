<?php

namespace App\Domain\Poe2;

use App\Models\Game;
use App\Models\GameVersion;
use RuntimeException;

/**
 * Resolves the active PoE2 game version once per request. All queries are
 * scoped to it so a fresh patch import can go live atomically.
 */
class Poe2Context
{
    protected ?GameVersion $version = null;

    public function version(): GameVersion
    {
        if ($this->version !== null) {
            return $this->version;
        }

        $game = Game::where('slug', 'poe2')->first();
        $version = $game?->activeVersion();

        if ($version === null) {
            throw new RuntimeException('No PoE2 game data has been imported yet. Run `php artisan poe2:import`.');
        }

        return $this->version = $version;
    }

    public function versionId(): int
    {
        return $this->version()->id;
    }
}
