<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'asset_id' => "system:icon/{$slug}",
            'path' => "system/icons/{$slug}.png",
            'origin' => fake()->randomElement(['game_integration', 'admin_upload', 'system']),
            'mimetype' => 'image/png',
            'filesize' => fake()->numberBetween(1000, 500000),
            'permissions' => 'public',
        ];
    }
}
