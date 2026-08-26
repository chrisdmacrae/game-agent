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

    'diablo-4' => [
        'name' => 'Diablo IV',

        // Datamined game files published as JSON by the DiabloTools/d4data project.
        // Only cloned directly in development; production reads the dist artifact.
        'repo_url' => env('D4_DATA_REPO_URL', 'https://github.com/DiabloTools/d4data.git'),
        'repo_ref' => env('D4_DATA_REPO_REF', 'master'),

        // Slim tarball + manifest.json built weekly by the d4-data-artifact CI
        // workflow. Either a signed/public URL or a Laravel filesystem disk.
        'dist_url' => env('D4_DATA_DIST_URL'),
        'dist_disk' => env('D4_DATA_DIST_DISK'),

        // Maxroll's compiled game data, used only to cross-check which content
        // is live versus unreleased/PTR.
        'maxroll_data_url' => env('D4_MAXROLL_DATA_URL', 'https://assets-ng.maxroll.gg/d4-tools/game/data.min.json'),

        'user_agent' => env('GAMES_USER_AGENT', 'game-agent-theorycrafter/0.1 (contact: admin@localhost)'),
    ],
];
