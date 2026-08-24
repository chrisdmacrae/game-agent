<?php

use App\Domain\Poe2\Import\UniqueTextParser;

test('extracts item blocks from lua', function () {
    $lua = <<<'LUA'
    -- Item data (c) Grinding Gear Games

    return {
    -- Amulet
    [[
    The Anvil
    Bloodstone Amulet
    Implicits: 1
    +(30-40) to maximum Life
    ]],[[
    Astramentis
    Stellar Amulet
    Implicits: 0
    +(50-100) to all Attributes
    ]]
    }
    LUA;

    $blocks = new UniqueTextParser()->blocksFromLua($lua);

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0])->toStartWith('The Anvil')
        ->and($blocks[1])->toStartWith('Astramentis');
});

test('parses a block with variants, tags, and implicits', function () {
    $block = <<<'TEXT'
    The Anvil
    Bloodstone Amulet
    Variant: Pre 0.2.0
    Variant: Current
    Implicits: 1
    {tags:life}+(30-40) to maximum Life
    {variant:1}20% increased Block chance
    {variant:1,2}{tags:defences}+(3-5)% to maximum Block chance
    TEXT;

    $parsed = new UniqueTextParser()->parseBlock($block);

    expect($parsed['name'])->toBe('The Anvil')
        ->and($parsed['base_name'])->toBe('Bloodstone Amulet')
        ->and($parsed['variants'])->toBe(['Pre 0.2.0', 'Current'])
        ->and($parsed['implicit_count'])->toBe(1)
        ->and($parsed['mods'])->toHaveCount(3);

    [$implicit, $legacy, $both] = $parsed['mods'];

    expect($implicit['is_implicit'])->toBeTrue()
        ->and($implicit['tags'])->toBe(['life'])
        ->and($implicit['text'])->toBe('+(30-40) to maximum Life')
        ->and($legacy['variants'])->toBe([1])
        ->and($both['variants'])->toBe([1, 2])
        ->and($both['tags'])->toBe(['defences']);
});

test('skips metadata directives like source and league', function () {
    $block = <<<'TEXT'
    Some Item
    Some Base
    League: Standard
    Source: Drops from a boss
    Implicits: 0
    10% increased Damage
    TEXT;

    $parsed = new UniqueTextParser()->parseBlock($block);

    expect($parsed['mods'])->toHaveCount(1)
        ->and($parsed['mods'][0]['text'])->toBe('10% increased Damage');
});
