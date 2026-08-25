<?php

namespace Database\Factories;

use App\Models\LoginLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoginLink>
 */
class LoginLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => null,
        ];
    }

    /**
     * Indicate that the link has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Indicate that the link has already been used.
     */
    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'consumed_at' => now()->subMinute(),
        ]);
    }
}
