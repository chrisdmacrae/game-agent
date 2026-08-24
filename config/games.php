<?php

return [
    'poe2' => [
        'name' => 'Path of Exile 2',

        // Datamined game data re-exported as JSON by the repoe-fork project.
        'repoe_base_url' => env('POE2_REPOE_BASE_URL', 'https://repoe-fork.github.io/poe2'),

        // Official passive tree export published by Grinding Gear Games.
        'tree_url' => env('POE2_TREE_URL', 'https://raw.githubusercontent.com/grindinggear/poe2-skilltree-export/master/data.json'),

        // Path of Building (PoE2 fork) hand-maintained unique item database.
        'pob_uniques_base_url' => env('POE2_POB_UNIQUES_BASE_URL', 'https://raw.githubusercontent.com/PathOfBuildingCommunity/PathOfBuilding-PoE2/dev/src/Data/Uniques'),
        'pob_uniques_files' => [
            'amulet', 'axe', 'belt', 'body', 'boots', 'bow', 'claw', 'crossbow',
            'dagger', 'flail', 'flask', 'focus', 'gloves', 'helmet', 'jewel',
            'mace', 'quiver', 'ring', 'sceptre', 'shield', 'soulcore', 'spear',
            'staff', 'sword', 'talisman', 'traptool', 'wand',
        ],

        // poe.ninja economy API (documented, public).
        'ninja_base_url' => env('POE2_NINJA_BASE_URL', 'https://poe.ninja/poe2/api/economy'),
        'ninja_league' => env('POE2_NINJA_LEAGUE', 'Runes of Aldur'),

        'user_agent' => env('GAMES_USER_AGENT', 'game-agent-theorycrafter/0.1 (contact: admin@localhost)'),
    ],
];
