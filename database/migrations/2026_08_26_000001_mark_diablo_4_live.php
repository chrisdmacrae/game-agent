<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Diablo IV goes live: the game data is imported, the build page and editor
     * render it, and the hub is game-agnostic. GameSeeder already seeds the row
     * with `is_live` set, but an environment seeded before this change still
     * holds the queued row, so flip it here too.
     */
    public function up(): void
    {
        DB::table('games')->where('slug', 'diablo-4')->update([
            'is_live' => true,
            'description' => 'Full game data: skills, aspects, uniques, affixes and the paragon boards.',
        ]);
    }

    public function down(): void
    {
        DB::table('games')->where('slug', 'diablo-4')->update([
            'is_live' => false,
            'description' => 'Season rotation makes the numbers move fast. Next in line after Last Epoch.',
        ]);
    }
};
