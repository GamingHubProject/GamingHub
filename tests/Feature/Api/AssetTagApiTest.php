<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\AssetTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssetTagApiTest extends TestCase
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

    public function test_index_is_public(): void
    {
        AssetTag::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/asset-tags');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_store_requires_the_admin_role(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/asset-tags', ['name' => 'Winter Event'])
            ->assertForbidden();
    }

    public function test_store_creates_a_tag_with_a_derived_slug(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/api/v1/asset-tags', ['name' => 'Winter Event']);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'winter-event');
    }

    public function test_store_is_idempotent_for_the_same_slug(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/v1/asset-tags', ['name' => 'Winter Event']);
        $this->actingAs($admin)->postJson('/api/v1/asset-tags', ['name' => 'Winter Event']);

        $this->assertSame(1, AssetTag::count());
    }

    public function test_destroy_requires_the_admin_role(): void
    {
        $tag = AssetTag::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/v1/asset-tags/{$tag->id}")
            ->assertForbidden();
    }

    public function test_asset_index_filters_by_tag(): void
    {
        $tag = AssetTag::factory()->create();
        $tagged = Asset::factory()->create();
        $tagged->tags()->attach($tag);
        Asset::factory()->create();

        $response = $this->getJson("/api/v1/assets?tag_id={$tag->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.items');
        $response->assertJsonPath('data.items.0.id', $tagged->id);
    }
}
