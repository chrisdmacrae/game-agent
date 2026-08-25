<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'short_name' => null,
            'accent' => 'teal-400',
            'icon' => 'swords',
            'is_live' => false,
            'sort_order' => 0,
            'description' => null,
        ];
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes) => ['is_live' => true]);
    }
}
