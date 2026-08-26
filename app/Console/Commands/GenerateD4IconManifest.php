<?php

namespace App\Console\Commands;

use App\Domain\D4\IconManifest;
use Illuminate\Console\Command;

class GenerateD4IconManifest extends Command
{
    protected $signature = 'd4:icon-manifest';

    protected $description = 'Write public/games/diablo-4/icon-manifest.json for the offline CASC icon extractor tool';

    public function handle(IconManifest $manifest): int
    {
        $count = $manifest->write();

        $this->info("Wrote icon manifest with {$count} texture atlases to public/games/diablo-4/icon-manifest.json");
        $this->line('Run tools/d4-icon-extractor on a machine with a Diablo IV install to produce the atlas sheets.');

        return self::SUCCESS;
    }
}
