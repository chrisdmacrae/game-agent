<?php

use App\Mcp\Servers\Poe2Server;
use App\Mcp\Tools\Poe2\PlanTreePathTool;
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

test('accepts a contiguous allocation from the class start', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'class' => 'Witch',
        'skills' => [['gem' => 'Spark']],
        'passives' => ['node_ids' => [1000, 1001]],
    ])
        ->assertOk()
        ->assertSee('"valid":true');
});

test('rejects a non-contiguous allocation', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'class' => 'Witch',
        'skills' => [['gem' => 'Spark']],
        'passives' => ['node_ids' => [1000, 1001, 1002]],
    ])
        ->assertOk()
        ->assertSee('not contiguous')
        ->assertSee('Heightened Curses');
});

test('granted nodes are exempt from pathing', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'class' => 'Witch',
        'skills' => [['gem' => 'Spark']],
        'passives' => [
            'node_ids' => [1000, 1001, 1002],
            'granted_nodes' => [['node_id' => 1002, 'source' => 'unique_jewel', 'detail' => 'From Nothing']],
        ],
    ])
        ->assertOk()
        ->assertSee('"valid":true');
});

test('instilled amulets can only grant notables', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'class' => 'Witch',
        'skills' => [['gem' => 'Spark']],
        'passives' => [
            'granted_nodes' => [['node_id' => 1001, 'source' => 'instilled_amulet']],
        ],
    ])
        ->assertOk()
        ->assertSee('instilling only allocates NOTABLE passives');
});

test('ascendancy nodes cannot be listed in node_ids', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'class' => 'Witch',
        'ascendancy' => 'Infernalist',
        'skills' => [['gem' => 'Spark']],
        'passives' => ['node_ids' => [2001]],
    ])
        ->assertOk()
        ->assertSee('passives.ascendancy_nodes instead');
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

test('granted instill must match the worn amulet when gear is structured', function () {
    $base = [
        'class' => 'Witch',
        'skills' => [['gem' => 'Spark']],
        'passives' => [
            'granted_nodes' => [['node_id' => 1002, 'source' => 'instilled_amulet']],
        ],
    ];

    // Gear present but the amulet has no matching instill -> violation.
    Poe2Server::tool(ValidateBuildTool::class, $base + [
        'gear' => [['slot' => 'amulet', 'rarity' => 'unique', 'name' => 'Astramentis']],
    ])
        ->assertOk()
        ->assertSee('no amulet in the build');

    // Matching instill -> valid.
    Poe2Server::tool(ValidateBuildTool::class, $base + [
        'gear' => [[
            'slot' => 'amulet',
            'rarity' => 'unique',
            'name' => 'Astramentis',
            'instill' => ['notable' => 'Heightened Curses'],
        ]],
    ])
        ->assertOk()
        ->assertSee('"valid":true');
});

test('granted unique_jewel requires a unique jewel in the build', function () {
    $base = [
        'class' => 'Witch',
        'skills' => [['gem' => 'Spark']],
        'passives' => [
            'granted_nodes' => [['node_id' => 1001, 'source' => 'unique_jewel', 'detail' => 'From Nothing']],
        ],
    ];

    Poe2Server::tool(ValidateBuildTool::class, $base + [
        'gear' => [['slot' => 'boots', 'rarity' => 'rare', 'mods' => ['30% increased Movement Speed']]],
    ])
        ->assertOk()
        ->assertSee('contains no unique jewel');

    Poe2Server::tool(ValidateBuildTool::class, $base + [
        'jewels' => [['name' => 'From Nothing', 'rarity' => 'unique']],
    ])
        ->assertOk()
        ->assertSee('"valid":true');
});

test('gear sanity checks: unknown uniques, duplicate slots, non-amulet instills', function () {
    Poe2Server::tool(ValidateBuildTool::class, [
        'class' => 'Witch',
        'skills' => [['gem' => 'Spark']],
        'gear' => [
            ['slot' => 'helmet', 'rarity' => 'unique', 'name' => 'Totally Fake Helm'],
            ['slot' => 'boots', 'rarity' => 'rare', 'instill' => ['notable' => 'Heightened Curses']],
            ['slot' => 'belt', 'rarity' => 'rare'],
            ['slot' => 'belt', 'rarity' => 'rare'],
        ],
    ])
        ->assertOk()
        ->assertSee('Unknown unique item')
        ->assertSee('only amulets can be instilled')
        ->assertSee('has 2 items');
});

test('plan_tree_path returns a contiguous allocation for named targets', function () {
    Poe2Server::tool(PlanTreePathTool::class, [
        'class' => 'Witch',
        'targets' => ['Chaos Inoculation'],
    ])
        ->assertOk()
        ->assertSee('"points_used":2')
        ->assertSee('Arcane Path');

    // The planned ids validate cleanly.
    Poe2Server::tool(ValidateBuildTool::class, [
        'class' => 'Witch',
        'skills' => [['gem' => 'Spark']],
        'passives' => ['node_ids' => [1000, 1001]],
    ])
        ->assertOk()
        ->assertSee('"valid":true');
});

test('plan_tree_path reports unreachable targets and unknown names', function () {
    Poe2Server::tool(PlanTreePathTool::class, [
        'class' => 'Witch',
        'targets' => ['Heightened Curses'],
    ])
        ->assertOk()
        ->assertSee('unreachable')
        ->assertSee('Heightened Curses');

    Poe2Server::tool(PlanTreePathTool::class, [
        'class' => 'Witch',
        'targets' => ['Made Up Node'],
    ])->assertHasErrors();
});
