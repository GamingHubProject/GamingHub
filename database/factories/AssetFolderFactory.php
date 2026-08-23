<?php

namespace Database\Factories;

use App\Models\AssetFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetFolder>
 */
class AssetFolderFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'parent_id' => null,
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'visibility' => 'public',
            'owner_id' => null,
            'path' => AssetFolder::buildPath(null, $slug),
            'created_by' => null,
        ];
    }

    public function adminOnly(): static
    {
        return $this->state(['visibility' => 'admin_only']);
    }

    public function userPrivate(?int $userId = null): static
    {
        return $this->state(fn () => [
            'visibility' => 'user_private',
            'owner_id' => $userId,
        ]);
    }
}
