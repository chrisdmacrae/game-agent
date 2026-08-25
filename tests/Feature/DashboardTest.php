<?php

namespace Tests\Feature;

use App\Models\SavedBuild;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\Poe2Seeder;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_the_dashboard_lists_only_the_users_own_builds()
    {
        $version = Poe2Seeder::seed();

        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownBuild = SavedBuild::create([
            'user_id' => $user->id,
            'game_id' => $version->game_id,
            'game_version_id' => $version->id,
            'name' => 'My Spark Build',
            'build' => ['class' => 'Witch', 'level' => 90, 'skills' => [['gem' => 'Spark']]],
        ]);

        SavedBuild::create([
            'user_id' => $other->id,
            'game_id' => $version->game_id,
            'game_version_id' => $version->id,
            'name' => 'Someone Elses Build',
            'build' => ['skills' => [['gem' => 'Spark']]],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('builds', 1)
                ->where('builds.0.id', $ownBuild->public_id)
                ->where('builds.0.name', 'My Spark Build')
                ->where('builds.0.level', 90)
            );
    }
}
