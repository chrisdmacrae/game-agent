<?php

namespace App\Domain\Builds;

use App\Models\Game;

/**
 * The classification options a build form and the hub filter rail offer:
 * classes and ascendancies come from the imported game data, stages and tiers
 * from the fixed taxonomies.
 *
 * What a class list means is per-game, so the lookups go through
 * GameBuildProfile; a game with nothing imported returns empty lists rather
 * than pretending to know its classes.
 */
class GameReference
{
    /** @return list<string> */
    public function classes(Game $game): array
    {
        return GameBuildProfile::for($game)->classes($this->versionId($game));
    }

    /**
     * Ascendancies, optionally narrowed to the selected classes — the hub's
     * ascendancy select depends on the class checkboxes above it.
     *
     * @param  list<string>  $classes
     * @return list<array{name: string, class_name: string|null}>
     */
    public function ascendancies(Game $game, array $classes = []): array
    {
        return GameBuildProfile::for($game)->ascendancies($this->versionId($game), $classes);
    }

    /** @return list<string> */
    public function stages(): array
    {
        return BuildStage::values();
    }

    /** @return list<string> */
    public function tiers(Game $game): array
    {
        return GameBuildProfile::for($game)->tiers();
    }

    protected function versionId(Game $game): ?int
    {
        return $game->activeVersion()?->id;
    }
}
