<?php

namespace App\Console\Commands;

use App\Domain\Poe2\Import\NinjaPriceImporter;
use Illuminate\Console\Command;

class ImportPoe2Prices extends Command
{
    protected $signature = 'poe2:prices {--league= : poe.ninja league id (defaults to config)}';

    protected $description = 'Import PoE2 currency exchange rates from poe.ninja';

    public function handle(NinjaPriceImporter $importer): int
    {
        $count = $importer->import($this->option('league'));

        $this->info("Imported {$count} price rows.");

        return self::SUCCESS;
    }
}
