<?php

use App\Models\Game;
use Database\Seeders\GameSeeder;

test('the game seeder seeds the four v1 games', function () {
    $this->seed(GameSeeder::class);

    expect(Game::count())->toBe(4)
        ->and(Game::pluck('slug')->sort()->values()->all())
        ->toBe(['diablo-4', 'last-epoch', 'poe2', 'wow']);

    $poe2 = Game::where('slug', 'poe2')->sole();

    expect($poe2->name)->toBe('Path of Exile 2')
        ->and($poe2->short_name)->toBe('PoE 2')
        ->and($poe2->is_live)->toBeTrue()
        ->and($poe2->accent)->toBe('teal-400')
        ->and($poe2->icon)->toBe('swords')
        ->and($poe2->sort_order)->toBe(0);

    $diablo4 = Game::where('slug', 'diablo-4')->sole();

    expect($diablo4->short_name)->toBe('D4')
        ->and($diablo4->is_live)->toBeTrue()
        ->and($diablo4->accent)->toBe('red-400');

    // PoE 2 and Diablo IV are live; Last Epoch and WoW still collect votes.
    expect(Game::where('is_live', false)->count())->toBe(2);
});

test('the go-live migration flips an already-seeded diablo-4 row', function () {
    Game::create(['slug' => 'diablo-4', 'name' => 'Diablo IV', 'is_live' => false]);

    (require database_path('migrations/2026_08_26_000001_mark_diablo_4_live.php'))->up();

    expect(Game::where('slug', 'diablo-4')->sole()->is_live)->toBeTrue();
});

test('the game seeder is idempotent and adopts a game the importer created', function () {
    // Poe2Importer creates the game with firstOrCreate and no presentation data.
    Game::create(['slug' => 'poe2', 'name' => 'Path of Exile 2']);

    $this->seed(GameSeeder::class);
    $this->seed(GameSeeder::class);

    expect(Game::count())->toBe(4)
        ->and(Game::where('slug', 'poe2')->sole()->icon)->toBe('swords');
});
