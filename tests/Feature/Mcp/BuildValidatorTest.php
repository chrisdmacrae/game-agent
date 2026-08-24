<?php

use App\Mcp\Servers\Poe2Server;
use App\Mcp\Tools\Poe2\ValidateBuildTool;
use Tests\Fixtures\Poe2Seeder;

beforeEach(function () {
    Poe2Seeder::seed();
});

test('a legal build validates cleanly', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'class' => 'Witch',
        'ascendancy' => 'Infernalist',
        'skills' => [
            ['gem' => 'Spark', 'supports' => ['Pierce']],
        ],
    ])
        ->assertOk()
        ->assertSee('"valid":true');
});

test('rejects duplicate support gems across skills', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'skills' => [
            ['gem' => 'Spark', 'supports' => ['Pierce']],
            ['gem' => 'Arctic Armour', 'supports' => ['Pierce']],
        ],
    ])
        ->assertOk()
        ->assertSee('only ONE copy of each support gem');
});

test('rejects incompatible support types', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'skills' => [
            ['gem' => 'Spark', 'supports' => ['Heavy Swing']],
        ],
    ])
        ->assertOk()
        ->assertSee('cannot support');
});

test('rejects unknown gems and mismatched ascendancy', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'class' => 'Witch',
        'ascendancy' => 'Deadeye',
        'skills' => [
            ['gem' => 'Totally Made Up Skill'],
        ],
    ])
        ->assertOk()
        ->assertSee('Unknown gem')
        ->assertSee('belongs to Ranger, not Witch');
});

test('flags spirit over budget', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'skills' => [
            ['gem' => 'Arctic Armour'],
        ],
        'spirit_available' => 20,
    ])
        ->assertOk()
        ->assertSee('Spirit over budget');
});

test('rejects more than five supports on one skill', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'skills' => [
            ['gem' => 'Spark', 'supports' => ['A', 'B', 'C', 'D', 'E', 'F']],
        ],
    ])
        ->assertOk()
        ->assertSee('maximum is 5');
});

test('warns on resistances below cap for endgame', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'skills' => [
            ['gem' => 'Spark', 'supports' => []],
        ],
        'resistances' => ['fire' => 40, 'cold' => 75, 'lightning' => 76],
        'content_tier' => 'endgame',
    ])
        ->assertOk()
        ->assertSee('Fire resistance 40%');
});

test('validates ascendancy nodes against the selected ascendancy', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'class' => 'Witch',
        'ascendancy' => 'Infernalist',
        'skills' => [['gem' => 'Spark']],
        'passives' => ['ascendancy_nodes' => ['Infernal Flame']],
    ])
        ->assertOk()
        ->assertSee('"valid":true');

    Poe2Server::tool(ValidateBuildTool::class, [
        'class' => 'Ranger',
        'ascendancy' => 'Deadeye',
        'skills' => [['gem' => 'Spark']],
        'passives' => ['ascendancy_nodes' => ['Infernal Flame']],
    ])
        ->assertOk()
        ->assertSee("was not found on Deadeye's ascendancy tree");

    Poe2Server::tool(ValidateBuildTool::class, [
        'skills' => [['gem' => 'Spark']],
        'passives' => ['ascendancy_nodes' => ['Infernal Flame']],
    ])
        ->assertOk()
        ->assertSee('no valid ascendancy');
});

test('flags unknown passives', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'skills' => [
            ['gem' => 'Spark'],
        ],
        'passives' => ['keystones' => ['Not A Real Keystone'], 'notables' => ['Heightened Curses']],
    ])
        ->assertOk()
        ->assertSee('Not A Real Keystone');
});
