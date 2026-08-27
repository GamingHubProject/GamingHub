<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssetFolderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_index_is_public_and_only_shows_public_folders_to_a_guest(): void
    {
        AssetFolder::factory()->create(['visibility' => 'public']);
        AssetFolder::factory()->adminOnly()->create();
        AssetFolder::factory()->userPrivate(User::factory()->create()->id)->create();

        $response = $this->getJson('/api/v1/asset-folders');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_admin_sees_every_folder_including_user_private(): void
    {
        AssetFolder::factory()->create(['visibility' => 'public']);
        AssetFolder::factory()->adminOnly()->create();
        AssetFolder::factory()->userPrivate(User::factory()->create()->id)->create();

        $response = $this->actingAs($this->admin())->getJson('/api/v1/asset-folders');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_a_user_sees_their_own_private_folder_but_not_someone_elses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        AssetFolder::factory()->userPrivate($user->id)->create();
        AssetFolder::factory()->userPrivate($other->id)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/asset-folders');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_store_requires_the_admin_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/asset-folders', [
            'name' => 'Maps',
            'visibility' => 'public',
        ])->assertForbidden();
    }

    public function test_store_creates_a_root_folder_with_a_derived_path(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/api/v1/asset-folders', [
            'name' => 'Maps',
            'visibility' => 'public',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.path', '/maps');
        $response->assertJsonPath('data.slug', 'maps');
    }

    public function test_store_creates_a_nested_folder_with_a_derived_path(): void
    {
        $parent = AssetFolder::factory()->create(['slug' => 'maps', 'path' => '/maps']);

        $response = $this->actingAs($this->admin())->postJson('/api/v1/asset-folders', [
            'name' => 'Palworld',
            'visibility' => 'public',
            'parent_id' => $parent->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.path', '/maps/palworld');
    }

    public function test_store_requires_owner_id_for_user_private_visibility(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/api/v1/asset-folders', [
            'name' => 'Private',
            'visibility' => 'user_private',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('owner_id');
    }

    public function test_rename_updates_own_path_and_cascades_to_descendants(): void
    {
        $admin = $this->admin();
        $parent = AssetFolder::factory()->create(['slug' => 'maps', 'path' => '/maps']);
        $child = AssetFolder::factory()->create(['parent_id' => $parent->id, 'slug' => 'palworld', 'path' => '/maps/palworld']);

        $response = $this->actingAs($admin)->patchJson("/api/v1/asset-folders/{$parent->id}", [
            'name' => 'Game Maps',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.path', '/game-maps');
        $this->assertSame('/game-maps/palworld', $child->fresh()->path);
    }

    public function test_move_rejects_moving_a_folder_into_its_own_descendant(): void
    {
        $admin = $this->admin();
        $parent = AssetFolder::factory()->create(['slug' => 'maps', 'path' => '/maps']);
        $child = AssetFolder::factory()->create(['parent_id' => $parent->id, 'slug' => 'palworld', 'path' => '/maps/palworld']);

        $response = $this->actingAs($admin)->patchJson("/api/v1/asset-folders/{$parent->id}", [
            'parent_id' => $child->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_destroy_rejects_a_folder_that_still_has_assets(): void
    {
        $admin = $this->admin();
        $folder = AssetFolder::factory()->create();
        Asset::factory()->create(['folder_id' => $folder->id]);

        $response = $this->actingAs($admin)->deleteJson("/api/v1/asset-folders/{$folder->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('asset_folders', ['id' => $folder->id]);
    }

    public function test_destroy_removes_an_empty_folder(): void
    {
        $admin = $this->admin();
        $folder = AssetFolder::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/v1/asset-folders/{$folder->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('asset_folders', ['id' => $folder->id]);
    }

    // --- Fonts folder (Theme font system) ---

    public function test_fonts_requires_the_admin_role(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/asset-folders/fonts')->assertForbidden();
    }

    public function test_fonts_lazily_creates_the_folder_on_first_call(): void
    {
        $this->assertDatabaseMissing('asset_folders', ['slug' => 'fonts']);

        $response = $this->actingAs($this->admin())->getJson('/api/v1/asset-folders/fonts');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'fonts');
        $response->assertJsonPath('data.name', 'Fonts');
        $response->assertJsonPath('data.visibility', 'admin_only');
        $this->assertDatabaseHas('asset_folders', ['slug' => 'fonts', 'parent_id' => null]);
    }

    public function test_fonts_is_idempotent_across_repeat_calls(): void
    {
        $admin = $this->admin();

        $first = $this->actingAs($admin)->getJson('/api/v1/asset-folders/fonts');
        $second = $this->actingAs($admin)->getJson('/api/v1/asset-folders/fonts');

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, AssetFolder::query()->where('slug', 'fonts')->count());
    }

    public function test_fonts_recreates_the_folder_if_it_was_deleted(): void
    {
        $admin = $this->admin();
        $first = $this->actingAs($admin)->getJson('/api/v1/asset-folders/fonts');
        $firstId = $first->json('data.id');

        AssetFolder::find($firstId)->delete();

        $second = $this->actingAs($admin)->getJson('/api/v1/asset-folders/fonts');

        $second->assertOk();
        $second->assertJsonPath('data.slug', 'fonts');
        $this->assertNotSame($firstId, $second->json('data.id'));
    }
}
