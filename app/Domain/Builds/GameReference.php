<?php

namespace App\Domain\Builds;

use App\Domain\Poe2\Validation\BuildRules;
use App\Models\Game;
use App\Models\Poe2\Ascendancy;
use App\Models\Poe2\CharacterClass;

/**
 * The classification options a build form and the hub filter rail offer:
 * classes and ascendancies come from the imported game data, stages and tiers
 * from the fixed taxonomies.
 *
 * PoE 2 is the only game with imported data today; every other game returns
 * empty lists rather than pretending to know its classes.
 */
class GameReference
{
    /** @return list<string> */
    public function classes(Game $game): array
    {
        $versionId = $this->versionId($game);

        if ($versionId === null) {
            return [];
        }

        return CharacterClass::query()
            ->forVersion($versionId)
            ->orderBy('name')
            ->pluck('name')
            ->all();
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
        $versionId = $this->versionId($game);

        if ($versionId === null) {
            return [];
        }

        return Ascendancy::query()
            ->forVersion($versionId)
            ->when($classes !== [], fn ($query) => $query->whereIn('class_name', $classes))
            ->orderBy('name')
            ->get(['name', 'class_name'])
            ->map(fn (Ascendancy $ascendancy) => [
                'name' => $ascendancy->name,
                'class_name' => $ascendancy->class_name,
            ])
            ->all();
    }

    /** @return list<string> */
    public function stages(): array
    {
        return BuildStage::values();
    }

    /** @return list<string> */
    public function tiers(): array
    {
        return BuildRules::TIERS;
    }

    protected function versionId(Game $game): ?int
    {
        if ($game->slug !== 'poe2') {
            return null;
        }

        return $game->activeVersion()?->id;
    }
}
