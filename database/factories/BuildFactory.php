<?php

namespace Database\Factories;

use App\Models\Build;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Build>
 */
class BuildFactory extends Factory
{
    protected $model = Build::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_id' => Game::factory(),
            'game_version_id' => null,
            'name' => Str::headline(rtrim(fake()->sentence(3), '.')),
            'summary' => fake()->sentence(),
            'guide_markdown' => null,
            'visibility' => Build::VISIBILITY_DRAFT,
            'build' => [
                'class' => 'Witch',
                'ascendancy' => 'Infernalist',
                'level' => 90,
                'skills' => [['gem' => 'Spark', 'supports' => [['name' => 'Pierce', 'effect' => null]]]],
            ],
            'validation' => ['valid' => true, 'violations' => [], 'warnings' => [], 'suggestions' => []],
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['visibility' => Build::VISIBILITY_DRAFT]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => ['visibility' => Build::VISIBILITY_PUBLIC]);
    }

    /**
     * A build carrying the numbers the hub sorts on, in both the payload and
     * the promoted columns.
     */
    public function withStats(): static
    {
        return $this->state(function (array $attributes) {
            $payload = array_merge($attributes['build'] ?? [], [
                'stage' => 'endgame',
                'tier' => 'A',
                'dps' => 4_100_000,
                'ehp' => 18_900,
                'cost_divine' => 12.5,
                'hardcore_viable' => true,
            ]);

            return ['build' => $payload];
        });
    }

    public function configure(): static
    {
        return $this->afterMaking(fn (Build $build) => $build->syncPromotedFields());
    }
}
