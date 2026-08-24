<?php

namespace App\Domain\Poe2\Queries;

use App\Domain\Poe2\Poe2Context;

class MetaQuery
{
    use CachesQueryResults;

    public function __construct(protected Poe2Context $context) {}

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->remember(__FUNCTION__, [], fn () => $this->uncachedContext());
    }

    /** @return array<string, mixed> */
    protected function uncachedContext(): array
    {
        $version = $this->context->version();

        return [
            'game' => 'Path of Exile 2',
            'game_state' => 'Early Access',
            'data_version' => $version->version,
            'league' => $version->league,
            'data_imported_at' => $version->imported_at?->toIso8601String(),
            'data_fingerprint' => $version->fingerprint,
            'data_sources' => [
                'game data' => 'repoe-fork JSON exports of datamined game files',
                'passive tree' => 'official Grinding Gear Games skill tree export',
                'unique items' => 'Path of Building (PoE2 fork) community database',
            ],
            'notes' => [
                'PoE2 is in Early Access; balance changes every few months can invalidate specifics.',
                'All numbers come from datamined game data, not simulation. This toolkit does not compute DPS.',
            ],
        ];
    }
}
