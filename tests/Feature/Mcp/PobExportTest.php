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
                ['slot' => 'amulet', 'rarity' => 'unique', 'name' => 'Astramentis', 'base' => 'Stellar Amulet'],
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
        ->toContain('<Slot name="Boots" itemId="1"/>');

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
