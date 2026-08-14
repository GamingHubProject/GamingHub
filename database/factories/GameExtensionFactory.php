<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameExtension>
 */
class GameExtensionFactory extends Factory
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
            'slug' => $slug,
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'version' => '0.1.000',
            'status' => 'disabled',
            'description' => fake()->sentence(),
        ];
    }
}
