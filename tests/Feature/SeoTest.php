<?php

use App\Models\SavedBuild;
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

    $build = SavedBuild::create([
        'user_id' => User::factory()->create()->id,
        'game_id' => $version->game_id,
        'game_version_id' => $version->id,
        'name' => 'Spark Stormweaver League Starter',
        'summary' => 'A lightning caster starter.',
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
