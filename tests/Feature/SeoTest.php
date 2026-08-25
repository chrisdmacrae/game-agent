<?php

use App\Models\Build;
use App\Models\Game;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\Poe2Seeder;

it('shares the canonical url for the home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('seo.url', route('home')));
});

it('shares the canonical url without the query string', function () {
    $this->get(route('home').'?utm_source=test')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('seo.url', route('home')));
});

/*
|--------------------------------------------------------------------------
| Page meta
|--------------------------------------------------------------------------
|
| The head tags come from the controller (App\Domain\Seo\PageMeta), so they
| are in the `seo` prop and, because <x-inertia::head> falls back to
| partials/seo.blade.php, in the served HTML as well.
|
*/

it('gives the landing page a title, a description and a card', function () {
    $response = $this->get(route('home'))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.title', 'Theorycraft with your assistant, publish for everyone else')
        ->where('seo.description', 'An MCP server that connects Claude or ChatGPT to real Path of Exile 2 game data. Ask for a build, get numbers back, and publish it for everyone else.')
        ->where('seo.ogImage', route('og-image'))
        ->where('seo.noindex', false));

    $response->assertSee('<title data-inertia="">Theorycraft with your assistant, publish for everyone else — Build Your Build</title>', false)
        ->assertSee('name="description" content="An MCP server that connects', false)
        ->assertSee('rel="canonical" href="'.route('home').'"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('property="og:url" content="'.route('home').'"', false)
        ->assertSee('property="og:image" content="'.route('og-image').'"', false)
        ->assertSee('name="twitter:card" content="summary_large_image"', false)
        ->assertDontSee('content="noindex, nofollow"', false);
});

it('titles the game hub after the game', function () {
    $game = Game::factory()->live()->create([
        'slug' => 'poe2',
        'name' => 'Path of Exile 2',
        'description' => 'Published Path of Exile 2 builds with real numbers.',
    ]);

    $response = $this->get(route('games.show', $game->slug))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Games/Hub')
        ->where('seo.title', 'Path of Exile 2 builds')
        ->where('seo.description', 'Published Path of Exile 2 builds with real numbers.'));

    $response->assertSee('<title data-inertia="">Path of Exile 2 builds — Build Your Build</title>', false)
        ->assertSee('content="Published Path of Exile 2 builds with real numbers."', false)
        ->assertSee('rel="canonical" href="'.route('games.show', $game->slug).'"', false);
});

it('keeps the hub canonical free of filter query strings', function () {
    $game = Game::factory()->live()->create(['slug' => 'poe2', 'name' => 'Path of Exile 2']);

    $this->get(route('games.show', $game->slug).'?sort=endorsed&view=list')
        ->assertOk()
        ->assertSee('rel="canonical" href="'.route('games.show', $game->slug).'"', false);
});

it('gives a waitlist game its own title and description', function () {
    $game = Game::factory()->create(['slug' => 'last-epoch', 'name' => 'Last Epoch']);

    $response = $this->get(route('games.show', $game->slug))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Games/Waitlist')
        ->where('seo.title', 'Last Epoch is not live yet')
        ->where('seo.description', 'Last Epoch is not wired up yet. Vote to move it up the queue and get told when it lands.'));

    $response->assertSee('<title data-inertia="">Last Epoch is not live yet — Build Your Build</title>', false)
        ->assertSee('rel="canonical" href="'.route('games.show', $game->slug).'"', false);
});

it('gives the sign-in page real meta rather than hiding it', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('seo.title', 'Sign in')
            ->where('seo.noindex', false))
        ->assertSee('<title data-inertia="">Sign in — Build Your Build</title>', false)
        ->assertSee('rel="canonical" href="'.route('login').'"', false);
});

it('marks session and private pages noindex', function (string $routeName) {
    $this->actingAs(User::factory()->create());

    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('seo.noindex', true))
        ->assertSee('name="robots" content="noindex, nofollow"', false)
        ->assertDontSee('rel="canonical"', false);
})->with(['my-builds', 'profile.edit']);

it('marks the verifying screen noindex', function () {
    $this->get(route('login.verify', 'a-token'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Verify')
            ->where('seo.title', 'Verifying')
            ->where('seo.noindex', true))
        ->assertSee('name="robots" content="noindex, nofollow"', false);
});

it('keeps a draft build out of the index', function () {
    $version = Poe2Seeder::seed();
    $user = User::factory()->create();

    $build = Build::create([
        'user_id' => $user->id,
        'game_id' => $version->game_id,
        'game_version_id' => $version->id,
        'name' => 'Spark Stormweaver',
        'summary' => 'A lightning caster starter.',
        'visibility' => Build::VISIBILITY_DRAFT,
        'build' => ['class' => 'Witch', 'ascendancy' => 'Stormweaver', 'level' => 90, 'skills' => [['gem' => 'Spark']]],
    ]);

    $this->actingAs($user)
        ->get($build->url())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.title', 'Spark Stormweaver')
            ->where('seo.description', 'A lightning caster starter.')
            ->where('seo.noindex', true));
});

it('describes a public build without a summary from its identity', function () {
    $version = Poe2Seeder::seed();

    $build = Build::create([
        'user_id' => User::factory()->create()->id,
        'game_id' => $version->game_id,
        'game_version_id' => $version->id,
        'name' => 'Spark Stormweaver',
        'visibility' => Build::VISIBILITY_PUBLIC,
        'build' => ['class' => 'Witch', 'ascendancy' => 'Stormweaver', 'level' => 90, 'skills' => [['gem' => 'Spark']]],
    ]);

    $this->get($build->url())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('seo.description', 'Witch · Stormweaver, level 90 build for Path of Exile 2.')
            ->where('seo.ogType', 'article')
            ->where('seo.ogImage', route('builds.og-image', $build->public_id))
            ->where('seo.noindex', false));
});

/*
|--------------------------------------------------------------------------
| Favicon
|--------------------------------------------------------------------------
*/

it('serves the BYB square mark in every format the head asks for', function () {
    $this->get(route('home'))
        ->assertSee('rel="icon" href="/favicon.svg" type="image/svg+xml"', false)
        ->assertSee('rel="apple-touch-icon" href="/apple-touch-icon.png"', false)
        ->assertSee('name="theme-color" content="#0E1116"', false);

    foreach (['favicon.svg', 'favicon.ico', 'favicon.png', 'apple-touch-icon.png'] as $file) {
        expect(public_path($file))->toBeFile();
    }

    $svg = file_get_contents(public_path('favicon.svg'));

    // Drawn, not set in a font, and in the brand's colours.
    expect($svg)->toContain('#0E1116')
        ->toContain('#2DE1C2')
        ->toContain('<path')
        ->not->toContain('<text');
});

it('renders the site og image as a png', function () {
    $response = $this->get(route('og-image'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect(substr($response->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");

    [$width, $height] = getimagesizefromstring($response->getContent());
    expect($width)->toBe(1200)->and($height)->toBe(630);
});

it('renders a build og image as a png', function () {
    $version = Poe2Seeder::seed();

    $build = Build::create([
        'user_id' => User::factory()->create()->id,
        'game_id' => $version->game_id,
        'game_version_id' => $version->id,
        'name' => 'Spark Stormweaver League Starter',
        'summary' => 'A lightning caster starter.',
        'visibility' => Build::VISIBILITY_PUBLIC,
        'build' => ['class' => 'Witch', 'ascendancy' => 'Infernalist', 'level' => 90, 'skills' => [['gem' => 'Spark']]],
    ]);

    $response = $this->get(route('builds.og-image', $build->public_id));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect(substr($response->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('returns 404 for an og image of an unknown build', function () {
    $this->get(route('builds.og-image', 'nope'))->assertNotFound();
});
