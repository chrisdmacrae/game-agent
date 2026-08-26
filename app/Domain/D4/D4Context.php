<?php

namespace App\Domain\D4;

use App\Models\Game;
use App\Models\GameVersion;
use RuntimeException;

/**
 * Resolves the active Diablo 4 game version once per request. All queries are
 * scoped to it so a fresh patch import can go live atomically.
 */
class D4Context
{
    protected ?GameVersion $version = null;

    public function version(): GameVersion
    {
        if ($this->version !== null) {
            return $this->version;
        }

        $game = Game::where('slug', 'diablo-4')->first();
        $version = $game?->activeVersion();

        if ($version === null) {
            throw new RuntimeException('No Diablo 4 game data has been imported yet. Run `php artisan d4:import`.');
        }

        return $this->version = $version;
    }

    public function versionId(): int
    {
        return $this->version()->id;
    }
}
