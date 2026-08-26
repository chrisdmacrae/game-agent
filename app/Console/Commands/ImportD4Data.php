<?php

namespace App\Console\Commands;

use App\Domain\D4\Import\D4DataSource;
use App\Domain\D4\Import\D4Importer;
use Illuminate\Console\Command;

class ImportD4Data extends Command
{
    protected $signature = 'd4:import
        {--from-git : Sparse-clone DiabloTools/d4data instead of reading the published artifact}
        {--refresh : Re-acquire source data instead of using the local tree}
        {--game-version= : Version label to import under, overriding the dump\'s buildVersion.txt}';

    protected $description = 'Import Diablo IV game data from the DiabloTools/d4data asset dump';

    public function handle(): int
    {
        // The dump decodes to hundreds of MB of arrays; the default CLI limit
        // is nowhere near enough for the Power and Item directories.
        ini_set('memory_limit', '2G');

        $source = new D4DataSource(fromGit: (bool) $this->option('from-git'));

        if ((bool) $this->option('refresh') || ! is_dir($source->treePath())) {
            $this->info('Acquiring the d4data source tree...');
            $source->acquire();
        }

        $version = $this->option('game-version');
        $version = is_string($version) && $version !== '' ? $version : null;

        $importer = new D4Importer($source);

        $this->info(sprintf('Importing Diablo IV data as version [%s]...', $version ?? $source->buildVersion()));

        $gameVersion = $importer->import($version);

        foreach ($importer->counts as $dataset => $count) {
            $this->line(sprintf('  %-20s %d', $dataset, $count));
        }

        $this->info("Done. Fingerprint: {$gameVersion->fingerprint}");

        return self::SUCCESS;
    }
}
