<?php

use App\Domain\Poe2\PobExporter;
use App\Mcp\Servers\Poe2Server;
use App\Mcp\Tools\Poe2\GetBuildTool;
use App\Mcp\Tools\Poe2\SaveBuildTool;
use App\Models\SavedBuild;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();
});

function savePobTestBuild(): SavedBuild
{
    Poe2Server::tool(SaveBuildTool::class, [
        'name' => 'Spark Starter',
        'build' => [
            'class' => 'Witch',
            'ascendancy' => 'Infernalist',
            'level' => 90,
            'skills' => [['gem' => 'Spark', 'supports' => ['Pierce']]],
            'passives' => [
                'node_ids' => [1000, 1001],
                'ascendancy_nodes' => ['Infernal Flame'],
            ],
            'gear' => [
                ['slot' => 'boots', 'rarity' => 'rare', 'name' => 'Swift Treads', 'mods' => ['30% increased Movement Speed']],
                ['slot' => 'amulet', 'rarity' => 'unique', 'name' => 'Astramentis'],
            ],
            'jewels' => [
                ['name' => 'From Nothing', 'rarity' => 'unique', 'socket_node_id' => 1003],
            ],
        ],
    ])->assertOk();

    return SavedBuild::sole();
}

test('the pob code decodes to importable build xml', function () {
    $build = savePobTestBuild();

    $code = app(PobExporter::class)->code($build);

    $xml = gzuncompress(base64_decode(strtr($code, '-_', '+/')));

    expect($xml)->toContain('<PathOfBuilding2>')
        ->toContain('className="Witch"')
        ->toContain('ascendClassName="Infernalist"')
        ->toContain('level="90"')
        ->toContain('targetVersion="0_1"')
        ->toContain('ascendClassId="1"')
        ->toContain('nameSpec="Spark"')
        ->toContain('nameSpec="Pierce"')
        ->toContain('nodes="1000,1001,2001"')
        ->toContain('Rarity: UNIQUE')
        ->toContain('Astramentis')
        // Unique base and mods resolve from the database, not agent input.
        ->toContain('Stellar Amulet')
        ->toContain('Implicits: 1')
        ->toContain('+(50-100) to all Attributes')
        ->toContain('<Slot name="Boots" itemId="1"/>')
        // Jewels bind to tree sockets inside the Spec.
        ->toContain('<Socket nodeId="1003" itemId="3"/>');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

test('the pob endpoint serves the code', function () {
    $build = savePobTestBuild();

    $this->get("/builds/{$build->public_id}/pob")
        ->assertOk()
        ->assertJsonStructure(['id', 'code', 'note'])
        ->assertJsonPath('id', $build->public_id);
});

test('save_build returns a pob code', function () {
    savePobTestBuild();

    Poe2Server::tool(GetBuildTool::class, ['id' => SavedBuild::sole()->public_id])
        ->assertOk()
        ->assertSee('pob_code');
});

test('loose rare mods materialize into concrete affix lines', function () {
    Poe2Server::tool(SaveBuildTool::class, [
        'name' => 'Materialize Test',
        'build' => [
            'class' => 'Witch',
            'skills' => [['gem' => 'Spark']],
            'gear' => [[
                'slot' => 'amulet',
                'rarity' => 'rare',
                'base' => 'Stellar Amulet',
                'mods' => ['increased Cast Speed', '+50 to maximum Life'],
            ]],
        ],
    ])->assertOk();

    $xml = app(PobExporter::class)->xml(SavedBuild::sole());

    // No-number line resolved to the best tier at item level 80, midpoint value.
    expect($xml)->toContain('12.5% increased Cast Speed')
        // Lines with concrete numbers pass through verbatim.
        ->toContain('+50 to maximum Life')
        ->not->toContain('>increased Cast Speed<');
});
