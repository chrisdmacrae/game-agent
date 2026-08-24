<?php

namespace App\Console\Commands;

use App\Domain\Poe2\IconManifest;
use Illuminate\Console\Command;

class GeneratePoe2IconManifest extends Command
{
    protected $signature = 'poe2:icon-manifest';

    protected $description = 'Write public/games/poe2/icon-manifest.json for the offline icon extractor tool';

    public function handle(IconManifest $manifest): int
    {
        $count = $manifest->write();

        $this->info("Wrote icon manifest with {$count} icons to public/games/poe2/icon-manifest.json");
        $this->line('Run tools/icon-extractor on a machine with ImageMagick to produce the icon files.');

        return self::SUCCESS;
    }
}
