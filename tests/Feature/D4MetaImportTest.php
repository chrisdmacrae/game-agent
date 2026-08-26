<?php

use App\Domain\D4\Meta\TierListImporter;
use App\Models\D4\MetaBuild;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * A trimmed but structurally faithful copy of the live Maxroll tier list page:
 * ten builds across every tier, both tierIndicator shapes, real guide links.
 */
function d4TierListPage(): string
{
    return file_get_contents(base_path('tests/Fixtures/maxroll/d4-tier-list.html'));
}

function fakeD4TierList(): void
{
    Http::fake(['maxroll.gg/*' => Http::response(d4TierListPage())]);
}

test('the importer parses the embedded remix payload into meta build rows', function () {
    fakeD4TierList();

    $result = app(TierListImporter::class)->import();

    expect($result['count'])->toBe(10)
        ->and($result['season'])->toBe('season-14-death-awakening')
        ->and($result['fetched'])->toBeTrue()
        ->and(MetaBuild::count())->toBe(10);

    $whirlwind = MetaBuild::where('name', 'Whirlwind Barb')->sole();

    expect($whirlwind->tier)->toBe('S')
        ->and($whirlwind->class_name)->toBe('Barbarian')
        ->and($whirlwind->source)->toBe('maxroll')
        ->and($whirlwind->season)->toBe('season-14-death-awakening')
        ->and($whirlwind->guide_url)->toBe('https://maxroll.gg/d4/build-guides/whirlwind-barbarian-guide')
        ->and($whirlwind->fetched_at)->not->toBeNull()
        ->and($whirlwind->raw['id'])->toBe('euu2ubybjhgn2g0qbe5w5can');

    expect(MetaBuild::where('name', 'Meteor Sorc')->value('class_name'))->toBe('Sorcerer')
        ->and(MetaBuild::where('tier', 'X')->pluck('name')->all())->toBe(['Penetrating Shot Rogue (Bugged)'])
        ->and(MetaBuild::pluck('tier')->unique()->sort()->values()->all())->toBe(['A', 'B', 'C', 'D', 'S', 'X']);
});

test('the importer records the new flag and the tier movement indicator as tags', function () {
    fakeD4TierList();

    app(TierListImporter::class)->import();

    // tierIndicator arrives either as a bare string or as a {value,label} object.
    expect(MetaBuild::where('name', 'Mighty Throw Barb')->value('tags'))->toContain('new')
        ->and(MetaBuild::where('name', 'Whirlwind Barb')->value('tags'))->toBe([]);
});

test('a re-run replaces the stored tier list instead of duplicating it', function () {
    fakeD4TierList();

    app(TierListImporter::class)->import();
    $firstIds = MetaBuild::pluck('id')->all();

    $result = app(TierListImporter::class)->import(force: true);

    expect($result['count'])->toBe(10)
        ->and(MetaBuild::count())->toBe(10)
        ->and(MetaBuild::pluck('id')->all())->not->toBe($firstIds)
        ->and(MetaBuild::where('name', 'Whirlwind Barb')->count())->toBe(1);
});

test('a fresh tier list is not re-fetched unless the import is forced', function () {
    fakeD4TierList();

    app(TierListImporter::class)->import();
    Http::assertSentCount(1);

    $result = app(TierListImporter::class)->import();

    expect($result['fetched'])->toBeFalse()
        ->and($result['count'])->toBe(10);

    Http::assertSentCount(1);

    app(TierListImporter::class)->import(force: true);

    Http::assertSentCount(2);
});

test('a page whose structure changed aborts without deleting the good rows', function (string $body) {
    Http::fakeSequence('maxroll.gg/*')
        ->push(d4TierListPage())
        ->push($body);

    app(TierListImporter::class)->import();

    expect(fn () => app(TierListImporter::class)->import(force: true))
        ->toThrow(RuntimeException::class);

    expect(MetaBuild::count())->toBe(10)
        ->and(MetaBuild::where('name', 'Whirlwind Barb')->exists())->toBeTrue();
})->with([
    'no embedded payload' => '<html><body><h1>Tier list</h1></body></html>',
    'unparsable payload' => '<html><body><script>window.__remixContext = {"state":;</script></body></html>',
    'no post in the payload' => '<html><body><script>window.__remixContext = {"state":{"loaderData":{"root":{}}}};</script></body></html>',
    'no tier list block' => '<html><body><script>window.__remixContext = {"state":{"loaderData":{"branch-posts":{"post":{"gutenbergBlock":[{"blockName":"core/paragraph"}]}}}}};</script></body></html>',
]);

test('a failed request leaves the stored tier list alone', function () {
    Sleep::fake();

    Http::fakeSequence('maxroll.gg/*')
        ->push(d4TierListPage())
        ->whenEmpty(Http::response('nope', 503));

    app(TierListImporter::class)->import();

    expect(fn () => app(TierListImporter::class)->import(force: true))
        ->toThrow(RuntimeException::class, 'HTTP 503');

    expect(MetaBuild::count())->toBe(10);
});

test('the d4:meta command reports the imported count and season', function () {
    fakeD4TierList();

    $this->artisan('d4:meta')
        ->expectsOutputToContain('Imported 10 meta builds for season-14-death-awakening.')
        ->assertSuccessful();

    $this->artisan('d4:meta')
        ->expectsOutputToContain('Tier list is still fresh: 10 builds for season-14-death-awakening.')
        ->assertSuccessful();

    $this->artisan('d4:meta', ['--refresh' => true])
        ->expectsOutputToContain('Imported 10 meta builds for season-14-death-awakening.')
        ->assertSuccessful();
});
