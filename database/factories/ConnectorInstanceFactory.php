<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConnectorInstance>
 */
class ConnectorInstanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'type' => 'rest',
            'base_url' => 'https://example.test',
            'credentials' => ['username' => 'admin', 'password' => fake()->password()],
            'status' => 'untested',
        ];
    }
}
