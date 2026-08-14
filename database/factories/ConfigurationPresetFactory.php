<?php

namespace Database\Factories;

use GamingHub\Core\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConfigurationPreset>
 */
class ConfigurationPresetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'name' => fake()->unique()->randomElement(['hardcore', 'casual', 'event', 'default']),
            'values' => ['ExpRate' => fake()->randomFloat(1, 0.1, 5)],
        ];
    }
}
