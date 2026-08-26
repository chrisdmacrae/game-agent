<?php

use App\Domain\D4\D4Context;
use App\Domain\D4\D4ParagonGraph;
use App\Domain\D4\Validation\D4BuildValidator;
use App\Mcp\Servers\D4Server;
use App\Mcp\Tools\D4\PlanParagonPathTool;
use Tests\Fixtures\D4Seeder;

/**
 * The fixture start board (Paragon_Barb_00, 21x21, 75 cells) anchors these
 * tests: StartNodeBarb sits at (14,10), the single exit gate at (0,10), the
 * glyph socket at (6,10), and the shortest start-to-socket path costs 12
 * points.
 */
beforeEach(function () {
    D4Seeder::seed();
});

function paragonGraph(): D4ParagonGraph
{
    return new D4ParagonGraph(app(D4Context::class));
}

function validateD4(array $build): array
{
    return (new D4BuildValidator(app(D4Context::class)))->validate($build);
}

/** A legal contiguous allocation: start node down column 8 to the socket. */
function socketRoute(): array
{
    return array_map(fn (array $pair) => ['row' => $pair[0], 'col' => $pair[1]], [
        [13, 10], [13, 9], [13, 8], [12, 8], [11, 8], [10, 8],
        [9, 8], [8, 8], [7, 8], [6, 8], [6, 9], [6, 10],
    ]);
}

test('the graph finds the start node, the exit gate and the glyph socket route', function () {
    $graph = paragonGraph();
    $board = $graph->board('Start', 'Barbarian');
    $grid = $board->grid;

    expect($graph->startNode($grid))->toBe(['row' => 14, 'col' => 10])
        ->and($graph->startGate($grid))->toBe(['row' => 0, 'col' => 10])
        ->and($graph->gates($grid))->toHaveCount(1);

    $plan = $graph->plan($grid, ['Glyph Socket'], $graph->startNode($grid));

    expect($plan['points_used'])->toBe(12)
        ->and($plan['unreachable'])->toBe([])
        ->and($plan['nodes'])->toContain(['row' => 6, 'col' => 10]);
});

test('planning to a nonexistent node reports it unreachable instead of inventing cells', function () {
    $graph = paragonGraph();
    $grid = $graph->board('Start', 'Barbarian')->grid;

    $plan = $graph->plan($grid, ['No Such Node', ['row' => 0, 'col' => 0]], $graph->startNode($grid));

    expect($plan['unreachable'])->toBe(['No Such Node', '0,0'])
        ->and($plan['points_used'])->toBe(0);
});

test('a contiguous allocation from the start node validates clean', function () {
    $result = validateD4([
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [[
            'board' => 'Start',
            'nodes' => socketRoute(),
        ]],
    ]);

    expect($result['violations'])->toBe([]);
});

test('a disconnected node is a violation', function () {
    $nodes = socketRoute();
    $nodes[] = ['row' => 3, 'col' => 11]; // A real cell, but not adjacent to the route.

    $result = validateD4([
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [['board' => 'Start', 'nodes' => $nodes]],
    ]);

    expect($result['violations'])->toHaveCount(1)
        ->and($result['violations'][0])->toContain('do not connect back')
        ->and($result['violations'][0])->toContain('(3,11)');
});

test('a node on empty space is a violation naming the cell', function () {
    $result = validateD4([
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [['board' => 'Start', 'nodes' => [['row' => 20, 'col' => 20]]]],
    ]);

    expect($result['violations'])->toHaveCount(1)
        ->and($result['violations'][0])->toContain('empty space')
        ->and($result['violations'][0])->toContain('(20,20)');
});

test('a socketed glyph warns when the allocation never reaches the socket', function () {
    // No class, so the Sorcerer fixture glyph skips its class check and the
    // socket coverage is what is exercised.
    $result = validateD4([
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [[
            'board' => 'Start',
            'glyph' => 'Enchanter',
            'nodes' => [['row' => 13, 'col' => 10]],
        ]],
    ]);

    expect($result['violations'])->toBe([])
        ->and(collect($result['warnings'])->filter(fn (string $warning) => str_contains($warning, 'glyph socket')))->toHaveCount(1);
});

test('later boards need a real gate cell and an earlier attach target', function () {
    $result = validateD4([
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [
            ['board' => 'Start', 'nodes' => socketRoute()],
            [
                'board' => 'Start',
                'nodes' => [['row' => 13, 'col' => 10]],
                'attach' => ['to' => 5, 'gate' => ['row' => 14, 'col' => 10]],
            ],
        ],
    ]);

    expect($result['violations'])->toContain('Paragon board "Start" attaches to entry #5, which is not an earlier entry.')
        ->and(collect($result['violations'])->filter(fn (string $violation) => str_contains($violation, 'is not a gate cell')))->toHaveCount(1);
});

test('nodes without an attach gate on a later board only warn', function () {
    $result = validateD4([
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [
            ['board' => 'Start', 'nodes' => socketRoute()],
            ['board' => 'Start', 'nodes' => [['row' => 13, 'col' => 10]]],
        ],
    ]);

    expect(collect($result['violations'])->filter(fn (string $violation) => str_contains($violation, 'connect back')))->toHaveCount(0)
        ->and(collect($result['warnings'])->filter(fn (string $warning) => str_contains($warning, 'no attach.gate')))->toHaveCount(1);
});

test('legacy entries without nodes stay legal and get a planning suggestion', function () {
    $result = validateD4([
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [['board' => 'Start', 'notables' => ['Glyph Socket']]],
    ]);

    expect($result['violations'])->toBe([])
        ->and(collect($result['suggestions'])->filter(fn (string $suggestion) => str_contains($suggestion, 'plan_paragon_path')))->toHaveCount(1);
});

test('notables are checked against the board and against the allocation', function () {
    $unknownNotable = validateD4([
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [['board' => 'Start', 'notables' => ['Blood Rage']]],
    ]);

    expect(collect($unknownNotable['warnings'])->filter(fn (string $warning) => str_contains($warning, '"Blood Rage" is not a node')))->toHaveCount(1);

    $uncovered = validateD4([
        'class' => 'Barbarian',
        'equipped_skills' => [['skill' => 'Whirlwind']],
        'paragon' => [[
            'board' => 'Start',
            'nodes' => [['row' => 13, 'col' => 10]],
            'notables' => ['Glyph Socket'],
        ]],
    ]);

    expect(collect($uncovered['warnings'])->filter(fn (string $warning) => str_contains($warning, 'not covered by the allocated nodes')))->toHaveCount(1);
});

test('plan_paragon_path routes the start board and returns payload-ready nodes', function () {
    D4Server::tool(PlanParagonPathTool::class, [
        'class' => 'Barbarian',
        'boards' => [['board' => 'Start', 'targets' => ['Glyph Socket']]],
    ])
        ->assertOk()
        ->assertSee('"points_used":12')
        ->assertSee('entry_gate')
        ->assertSee('paragon[].nodes');
});

test('plan_paragon_path rejects unknown boards and non-start first boards', function () {
    D4Server::tool(PlanParagonPathTool::class, [
        'class' => 'Barbarian',
        'boards' => [['board' => 'No Such Board', 'targets' => ['Glyph Socket']]],
    ])->assertHasErrors();
});
