<?php

namespace App\Console\Commands;

use App\Domain\D4\Meta\TierListImporter;
use Illuminate\Console\Command;

class ImportD4Meta extends Command
{
    protected $signature = 'd4:meta {--refresh : Re-fetch even when the stored tier list is still fresh}';

    protected $description = 'Import the Diablo IV editorial endgame tier list from Maxroll';

    public function handle(TierListImporter $importer): int
    {
        $result = $importer->import((bool) $this->option('refresh'));

        $season = $result['season'] ?? 'unknown season';

        if (! $result['fetched']) {
            $this->info("Tier list is still fresh: {$result['count']} builds for {$season}. Use --refresh to re-fetch.");

            return self::SUCCESS;
        }

        $this->info("Imported {$result['count']} meta builds for {$season}.");

        return self::SUCCESS;
    }
}
