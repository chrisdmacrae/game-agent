<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

/**
 * The games shown on the root landing page. PoE 2 and Diablo IV are live; the
 * rest are queued and collect waitlist votes.
 *
 * Idempotent on slug so it can run alongside Poe2Importer, which creates the
 * poe2 row with firstOrCreate.
 */
class GameSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    protected const GAMES = [
        [
            'slug' => 'poe2',
            'name' => 'Path of Exile 2',
            'short_name' => 'PoE 2',
            'accent' => 'teal-400',
            'icon' => 'swords',
            'is_live' => true,
            'sort_order' => 0,
            'description' => 'Full game data: gems, supports, uniques, affixes and the passive tree.',
        ],
        [
            'slug' => 'last-epoch',
            'name' => 'Last Epoch',
            'short_name' => 'LE',
            'accent' => 'blue-400',
            'icon' => 'clock',
            'is_live' => false,
            'sort_order' => 1,
            'description' => 'Skill-tree data is mapped. Waiting on the item affix tables before it goes live.',
        ],
        [
            'slug' => 'diablo-4',
            'name' => 'Diablo IV',
            'short_name' => 'D4',
            'accent' => 'red-400',
            'icon' => 'skull',
            'is_live' => true,
            'sort_order' => 2,
            'description' => 'Full game data: skills, aspects, uniques, affixes and the paragon boards.',
        ],
        [
            'slug' => 'wow',
            'name' => 'World of Warcraft',
            'short_name' => 'WoW',
            'accent' => 'gold-400',
            'icon' => 'crown',
            'is_live' => false,
            'sort_order' => 3,
            'description' => 'Talent loadouts and sim integration. The biggest job on the list.',
        ],
    ];

    public function run(): void
    {
        foreach (self::GAMES as $game) {
            Game::updateOrCreate(['slug' => $game['slug']], $game);
        }
    }
}
