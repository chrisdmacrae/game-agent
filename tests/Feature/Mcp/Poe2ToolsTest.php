<?php

use App\Mcp\Servers\Poe2Server;
use App\Mcp\Tools\Poe2\GetAscendancyTool;
use App\Mcp\Tools\Poe2\GetGemTool;
use App\Mcp\Tools\Poe2\GetMetaContextTool;
use App\Mcp\Tools\Poe2\GetSupportsForGemTool;
use App\Mcp\Tools\Poe2\GetUniqueTool;
use App\Mcp\Tools\Poe2\ListClassesTool;
use App\Mcp\Tools\Poe2\SearchGemsTool;
use App\Mcp\Tools\Poe2\SearchPassivesTool;
use App\Mcp\Tools\Poe2\SearchUniquesTool;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();
});

test('get_meta_context reports the active data version', function () {
    Poe2Server::tool(GetMetaContextTool::class)
        ->assertOk()
        ->assertSee('0.5.2-test')
        ->assertSee('Test League');
});

test('list_classes includes ascendancies', function () {
    Poe2Server::tool(ListClassesTool::class)
        ->assertOk()
        ->assertSee('Witch')
        ->assertSee('Infernalist');
});

test('search_gems filters by term and tags', function () {
    Poe2Server::tool(SearchGemsTool::class, ['term' => 'spark'])
        ->assertOk()
        ->assertSee('Spark');

    Poe2Server::tool(SearchGemsTool::class, ['tags' => ['persistent']])
        ->assertOk()
        ->assertSee('Arctic Armour')
        ->assertDontSee('"Spark"');
});

test('get_gem returns stat text at requested level and spirit reservation', function () {
    Poe2Server::tool(GetGemTool::class, ['name' => 'Spark', 'level' => 20])
        ->assertOk()
        ->assertSee('Deals 40 to 120 Lightning Damage')
        ->assertSee('Fires 3 Projectiles');

    Poe2Server::tool(GetGemTool::class, ['name' => 'Arctic Armour'])
        ->assertOk()
        ->assertSee('spirit_reservation');
});

test('get_gem errors helpfully for unknown gems', function () {
    Poe2Server::tool(GetGemTool::class, ['name' => 'Fireball From PoE1'])
        ->assertHasErrors();
});

test('get_supports_for_gem excludes incompatible supports and flags recommended ones', function () {
    $response = Poe2Server::tool(GetSupportsForGemTool::class, ['gem' => 'Spark'])
        ->assertOk()
        ->assertSee('Pierce')
        ->assertDontSee('Heavy Swing'); // excludes Spell skills

    $response->assertSee('is_recommended');
});

test('search_uniques and get_unique resolve current variant mods', function () {
    Poe2Server::tool(SearchUniquesTool::class, ['item_class' => 'Amulet'])
        ->assertOk()
        ->assertSee('Astramentis');

    Poe2Server::tool(GetUniqueTool::class, ['name' => 'Astramentis'])
        ->assertOk()
        ->assertSee('+(50-100) to all Attributes');
});

test('search_passives finds keystones by stat text', function () {
    Poe2Server::tool(SearchPassivesTool::class, ['term' => 'Chaos'])
        ->assertOk()
        ->assertSee('Chaos Inoculation');
});

test('get_ascendancy returns its nodes', function () {
    Poe2Server::tool(GetAscendancyTool::class, ['name' => 'Infernalist'])
        ->assertOk()
        ->assertSee('Infernal Flame');
});
