<?php

use App\Domain\D4\D4BuildPageEnricher;
use App\Models\Build;
use App\Models\User;
use Tests\Fixtures\D4Seeder;

beforeEach(function () {
    $this->version = D4Seeder::seed();
});

function enrichD4(array $payload, ?string $guideMarkdown = null, ?string $guideHtml = null): array
{
    $build = Build::factory()
        ->for(User::factory()->create())
        ->for(test()->version->game)
        ->create([
            'game_version_id' => test()->version->id,
            'build' => $payload,
            'guide_markdown' => $guideMarkdown,
        ]);

    return app(D4BuildPageEnricher::class)->enrich($build, $guideHtml);
}

test('every referenced skill, aspect, unique, glyph and notable becomes an entity', function () {
    $enriched = enrichD4([
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind', 'rank' => 5]],
        'gear' => [
            'helm' => ['aspect' => 'of Berserk Ripping'],
            'weapons' => [['name' => "Ancients' Oath", 'rarity' => 'unique']],
        ],
        'paragon' => [[
            'board' => 'Start',
            'glyph' => 'Enchanter',
            'notables' => ['Glyph Socket'],
        ]],
    ]);

    $entities = $enriched['entities'];

    expect($entities)->toHaveKeys(['Whirlwind', 'of Berserk Ripping', "Ancients' Oath", 'Enchanter', 'Glyph Socket'])
        ->and($entities['Whirlwind']['kind'])->toBe('skill')
        ->and($entities['Whirlwind']['rank'])->toBe(5)
        // TooltipText substitutes the rank-5 numbers into the description.
        ->and($entities['Whirlwind']['description'])->toContain('Fury Cost:')
        ->and($entities['of Berserk Ripping']['kind'])->toBe('aspect')
        ->and($entities["Ancients' Oath"]['kind'])->toBe('unique')
        ->and($entities['Enchanter']['kind'])->toBe('glyph')
        ->and($entities['Enchanter']['effects'])->not->toBeEmpty()
        ->and($entities['Glyph Socket']['kind'])->toBe('paragon-node')
        ->and($entities['Glyph Socket']['board'])->toBe('Start');

    // No atlas sheets are extracted in the test environment, so icons are
    // null and the UI letter-badges them.
    expect($entities['Whirlwind']['icon'])->toBeNull();
});

test('guide mentions are collected and tagged with data-entity spans', function () {
    $enriched = enrichD4(
        ['equipped_skills' => [['skill' => 'Whirlwind']]],
        guideMarkdown: "Spin to win with Whirlwind and Ancients' Oath.",
        guideHtml: "<p>Spin to win with Whirlwind and Ancients' Oath.</p>",
    );

    expect($enriched['entities'])->toHaveKeys(['Whirlwind', "Ancients' Oath"])
        ->and($enriched['guide_html'])->toContain('data-entity="Whirlwind"')
        ->and($enriched['guide_html'])->toContain('data-entity="Ancients&#039; Oath"');
});
