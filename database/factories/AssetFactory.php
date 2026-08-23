<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        $path = 'assets/2026/08/'.fake()->regexify('[a-f0-9]{16}').'.png';

        return [
            'owner_type' => null,
            'owner_id' => null,
            'disk_path' => $path,
            'url' => 'http://localhost/storage/'.$path,
            'mime_type' => 'image/png',
            'size' => fake()->numberBetween(1000, 500000),
            'width' => 800,
            'height' => 400,
            'alt_text' => null,
            'uploaded_by' => null,
        ];
    }
}
