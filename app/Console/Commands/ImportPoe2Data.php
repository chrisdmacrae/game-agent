<?php

namespace App\Console\Commands;

use App\Domain\Poe2\Import\DataSourceClient;
use App\Domain\Poe2\Import\Poe2Importer;
use App\Domain\Poe2\Import\UniqueTextParser;
use Illuminate\Console\Command;

class ImportPoe2Data extends Command
{
    protected $signature = 'poe2:import
        {--game-version= : Patch version label to import under (e.g. 0.5.2)}
        {--league= : Current league name (e.g. "Return of the Ancients")}
        {--refresh : Re-download source data instead of using the local cache}';

    protected $description = 'Import Path of Exile 2 game data from repoe-fork, the official passive tree export, and Path of Building';

    public function handle(): int
    {
        // The source exports decode to several hundred MB of arrays (skills.json
        // alone is 14MB of JSON); the default CLI limit is far too small.
        ini_set('memory_limit', '2G');

        $version = $this->option('game-version') ?? '0.5.x';
        $league = $this->option('league');

        $client = new DataSourceClient(refresh: (bool) $this->option('refresh'));
        $importer = new Poe2Importer($client, new UniqueTextParser);

        $this->info("Importing PoE2 data as version [{$version}]...");

        $gameVersion = $importer->import($version, $league);

        foreach ($importer->counts as $dataset => $count) {
            $this->line(sprintf('  %-20s %d', $dataset, $count));
        }

        $this->info("Done. Fingerprint: {$gameVersion->fingerprint}");

        return self::SUCCESS;
    }
}
