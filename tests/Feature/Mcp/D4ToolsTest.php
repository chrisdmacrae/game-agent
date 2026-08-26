<?php

use App\Domain\Games\ModelDocRepository;
use App\Mcp\Servers\D4Server;
use App\Mcp\Tools\D4\GetGameModelTool;
use App\Mcp\Tools\D4\GetMetaContextTool;
use App\Mcp\Tools\D4\GetParagonBoardTool;
use App\Mcp\Tools\D4\GetSkillTool;
use App\Mcp\Tools\D4\GetUniqueTool;
use App\Mcp\Tools\D4\ListClassesTool;
use App\Mcp\Tools\D4\ListGameModelsTool;
use App\Mcp\Tools\D4\SearchAffixesTool;
use App\Mcp\Tools\D4\SearchAspectsTool;
use App\Mcp\Tools\D4\SearchGameKnowledgeTool;
use App\Mcp\Tools\D4\SearchGlyphsTool;
use App\Mcp\Tools\D4\SearchSkillsTool;
use App\Mcp\Tools\D4\SearchUniquesTool;
use App\Models\D4\MetaBuild;
use Tests\Fixtures\D4Seeder;

beforeEach(function () {
    D4Seeder::seed();
});

test('get_meta_context reports the seeded patch, its class roster and the standing caveats', function () {
    D4Server::tool(GetMetaContextTool::class)
        ->assertOk()
        ->assertSee('3.1.3.73224')
        ->assertSee('fixturecommitsha')
        ->assertSee('Barbarian')
        ->assertSee('dataset_counts')
        ->assertSee('no economy or trade data')
        ->assertSee('no telemetry-based meta')
        ->assertSee('does not carry a season name');
});

test('get_meta_context says the tier list has not been ingested when the table is empty', function () {
    D4Server::tool(GetMetaContextTool::class)
        ->assertOk()
        ->assertSee('not_ingested')
        ->assertSee('No tier list data has been ingested yet');
});

test('get_meta_context adds the attributed tier list once meta builds are ingested', function () {
    MetaBuild::insert([
        [
            'source' => 'maxroll',
            'season' => 'season-14-death-awakening',
            'name' => 'Whirlwind Barb',
            'class_name' => 'Barbarian',
            'tier' => 'S',
            'tags' => '[]',
            'guide_url' => 'https://maxroll.gg/d4/build-guides/whirlwind-barbarian-guide',
            'raw' => '{}',
            'fetched_at' => now(),
        ],
        [
            'source' => 'maxroll',
            'season' => 'season-14-death-awakening',
            'name' => 'Meteor Sorc',
            'class_name' => 'Sorcerer',
            'tier' => 'D',
            'tags' => '["down1"]',
            'guide_url' => 'https://maxroll.gg/d4/build-guides/meteor-sorcerer-guide',
            'raw' => '{}',
            'fetched_at' => now(),
        ],
    ]);

    D4Server::tool(GetMetaContextTool::class)
        ->assertOk()
        ->assertSee('season-14-death-awakening')
        ->assertSee('editorial tier list data from Maxroll')
        ->assertSee('https://maxroll.gg/d4/tierlists/endgame-tier-list')
        ->assertSee(now()->toDateString())
        ->assertSee('Whirlwind Barb')
        ->assertSee('whirlwind-barbarian-guide')
        ->assertSee('Meteor Sorc')
        ->assertDontSee('not_ingested');
});

test('list_classes returns the resource and skill count for each class', function () {
    D4Server::tool(ListClassesTool::class)
        ->assertOk()
        ->assertSee('Barbarian')
        ->assertSee('fury')
        ->assertSee('skill_count');
});

test('search_skills filters by text, class, category and tag', function () {
    D4Server::tool(SearchSkillsTool::class, ['query' => 'whirlwind'])
        ->assertOk()
        ->assertSee('Whirlwind')
        ->assertDontSee('Chain Lightning');

    D4Server::tool(SearchSkillsTool::class, ['class' => 'Sorcerer'])
        ->assertOk()
        ->assertSee('Chain Lightning')
        ->assertDontSee('Whirlwind');

    D4Server::tool(SearchSkillsTool::class, ['category' => 'core'])
        ->assertOk()
        ->assertSee('Whirlwind')
        ->assertSee('Chain Lightning');

    D4Server::tool(SearchSkillsTool::class, ['tag' => 'Skill_Channeled'])
        ->assertOk()
        ->assertSee('Whirlwind')
        ->assertDontSee('Chain Lightning');
});

test('get_skill roundtrips by name and by sno_id with its enhancements intact', function () {
    D4Server::tool(GetSkillTool::class, ['name' => 'Whirlwind'])
        ->assertOk()
        ->assertSee('Rapidly attack surrounding enemies')
        ->assertSee('Tornado')
        ->assertSee('Skill_Channeled')
        ->assertSee('max_rank');

    D4Server::tool(GetSkillTool::class, ['sno_id' => 206435])
        ->assertOk()
        ->assertSee('Whirlwind');
});

test('get_skill renders its text for the requested rank and keeps the raw text beside it', function () {
    D4Server::tool(GetSkillTool::class, ['name' => 'Whirlwind'])
        ->assertOk()
        // The rendered upgrade text carries the real number the token stood for...
        ->assertSee('Gain 12%[x] increased Movement Speed during Whirlwind.')
        // ...while the raw string it came from stays available.
        ->assertSee('[{SF_35}*100|x%|]')
        ->assertSee('description_rendered')
        ->assertSee('rank_values')
        // Tokens backed by structures we have not resolved survive as tokens.
        ->assertSee('{payload:DAMAGE_TOOLTIP}')
        ->assertSee('"rank":1');

    // SF_0 is 1.65 * the skill rank bonus table, so a higher rank moves it.
    D4Server::tool(GetSkillTool::class, ['name' => 'Whirlwind', 'rank' => 5])
        ->assertOk()
        ->assertSee('"rank":5');

    // Out-of-range ranks clamp rather than erroring or rendering nothing.
    D4Server::tool(GetSkillTool::class, ['name' => 'Whirlwind', 'rank' => 99])
        ->assertOk()
        ->assertSee('"rank":15');
});

test('search_skills carries a rendered description alongside the raw one', function () {
    D4Server::tool(SearchSkillsTool::class, ['query' => 'whirlwind'])
        ->assertOk()
        ->assertSee('description_rendered')
        ->assertSee('Rapidly attack surrounding enemies');
});

test('get_skill errors helpfully without a lookup key or for an unknown skill', function () {
    D4Server::tool(GetSkillTool::class, [])->assertHasErrors();
    D4Server::tool(GetSkillTool::class, ['name' => 'Blizzard From Diablo 2'])->assertHasErrors();
});

test('search_uniques and get_unique roundtrip, keeping unresolved forced affixes', function () {
    D4Server::tool(SearchUniquesTool::class, ['query' => 'Ancients'])
        ->assertOk()
        ->assertSee("Ancients' Oath")
        ->assertSee('Axe2H');

    D4Server::tool(SearchUniquesTool::class, ['class' => 'Barbarian'])
        ->assertOk()
        ->assertSee("Ancients' Oath");

    D4Server::tool(GetUniqueTool::class, ['name' => "Ancients' Oath"])
        ->assertOk()
        ->assertSee('Steel Grasp')
        // The second forced affix falls outside the fixture slice and comes
        // back as a bare reference rather than being dropped.
        ->assertSee('S04_Damage_All');

    D4Server::tool(GetUniqueTool::class, ['name' => 'Grandfather'])->assertHasErrors();
    D4Server::tool(GetUniqueTool::class, [])->assertHasErrors();
});

test('search_affixes filters by text, tempering, magic type and class', function () {
    D4Server::tool(SearchAffixesTool::class, ['query' => 'Critical Strike Chance'])
        ->assertOk()
        ->assertSee('CritChance');

    D4Server::tool(SearchAffixesTool::class, ['is_tempering' => true])
        ->assertOk()
        ->assertSee('Tempered_AttackSpeed_Sorc_Tag_Pyromancy_Tier1')
        ->assertDontSee('"CritChance"');

    D4Server::tool(SearchAffixesTool::class, ['temper_family' => 'AttackSpeed_Sorc_Tag_Pyromancy'])
        ->assertOk()
        ->assertSee('Tempered_AttackSpeed_Sorc_Tag_Pyromancy_Tier1');

    D4Server::tool(SearchAffixesTool::class, ['magic_type' => 'power'])
        ->assertOk()
        ->assertSee('legendary_barb_001')
        ->assertDontSee('"CritChance"');

    D4Server::tool(SearchAffixesTool::class, ['class' => 'Barbarian'])
        ->assertOk()
        ->assertSee('legendary_barb_001')
        ->assertDontSee('Tempered_AttackSpeed_Sorc_Tag_Pyromancy_Tier1');
});

test('search_aspects filters by text, category and class', function () {
    D4Server::tool(SearchAspectsTool::class, ['query' => 'Berserk'])
        ->assertOk()
        ->assertSee('of Berserk Ripping')
        ->assertSee('offensive');

    D4Server::tool(SearchAspectsTool::class, ['category' => 'offensive', 'class' => 'Barbarian'])
        ->assertOk()
        ->assertSee('of Berserk Ripping');

    D4Server::tool(SearchAspectsTool::class, ['class' => 'Sorcerer'])
        ->assertOk()
        ->assertDontSee('of Berserk Ripping');

    D4Server::tool(SearchAspectsTool::class, ['item_type' => 'Axe'])
        ->assertOk()
        ->assertSee('of Berserk Ripping');
});

test('search_glyphs matches names and effect text and scopes to a class', function () {
    D4Server::tool(SearchGlyphsTool::class, ['query' => 'Enchanter'])
        ->assertOk()
        ->assertSee('Enchanter')
        ->assertSee('Intelligence_Core');

    D4Server::tool(SearchGlyphsTool::class, ['query' => 'purchased within range'])
        ->assertOk()
        ->assertSee('Enchanter');

    D4Server::tool(SearchGlyphsTool::class, ['class' => 'Barbarian'])
        ->assertOk()
        ->assertDontSee('Enchanter');
});

test('get_paragon_board lists boards without a name and returns the grid with one', function () {
    D4Server::tool(GetParagonBoardTool::class)
        ->assertOk()
        ->assertSee('Start')
        ->assertSee('node_count')
        ->assertSee('socket_count')
        ->assertDontSee('grid_legend');

    D4Server::tool(GetParagonBoardTool::class, ['name' => 'Start', 'class' => 'Barbarian'])
        ->assertOk()
        ->assertSee('grid_legend')
        ->assertSee('Glyph Socket')
        ->assertSee('Barbarian_Rare_015');

    D4Server::tool(GetParagonBoardTool::class, ['name' => 'No Such Board'])->assertHasErrors();
});

test('listings hide unreleased fixture junk unless include_unreleased is passed', function () {
    D4Server::tool(SearchUniquesTool::class)
        ->assertOk()
        ->assertSee("Ancients' Oath")
        ->assertDontSee('VFX Testing Hat');

    D4Server::tool(SearchUniquesTool::class, ['include_unreleased' => true])
        ->assertOk()
        ->assertSee('VFX Testing Hat');

    // The scratch affix carries no text at all, so it stays out of the affix
    // pool either way rather than surfacing as an empty row.
    D4Server::tool(SearchAffixesTool::class, ['include_unreleased' => true, 'limit' => 100])
        ->assertOk()
        ->assertDontSee('TEST_Legendary_Power1');
});

test('the game model doc tools work against whatever docs are on disk', function () {
    $docs = app(ModelDocRepository::class)->all('diablo-4');

    $response = D4Server::tool(ListGameModelsTool::class)->assertOk();

    foreach ($docs as $doc) {
        $response->assertSee($doc['id']);

        D4Server::tool(GetGameModelTool::class, ['id' => $doc['id']])
            ->assertOk()
            ->assertSee($doc['title']);
    }

    D4Server::tool(GetGameModelTool::class, ['id' => 'no-such-model'])->assertHasErrors();
});

test('search_game_knowledge finds a document when one exists and stays empty when none do', function () {
    $docs = app(ModelDocRepository::class)->all('diablo-4');

    if ($docs->isEmpty()) {
        D4Server::tool(SearchGameKnowledgeTool::class, ['query' => 'damage'])
            ->assertOk()
            ->assertSee('[]');

        return;
    }

    $first = $docs->first();
    $word = collect(preg_split('/\s+/', $first['title']))
        ->first(fn (string $part) => mb_strlen($part) > 3) ?? $first['title'];

    D4Server::tool(SearchGameKnowledgeTool::class, ['query' => $word])
        ->assertOk()
        ->assertSee('snippet');
});
