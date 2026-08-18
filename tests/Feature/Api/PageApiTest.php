<?php

namespace Tests\Feature\Api;

use App\Models\Page;
use App\Models\User;
use App\Permissions\ScopedPermissionName;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_root_level_published_page_returns_json(): void
    {
        Page::create(['title' => 'Community', 'slug' => 'community', 'type' => 'page', 'status' => 'published']);

        $response = $this->getJson('/api/v1/pages/community');

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Community');
        $response->assertJsonPath('data.path', 'community');
    }

    public function test_a_nested_published_page_resolves_through_its_folder_path(): void
    {
        $games = Page::create(['title' => 'Games', 'slug' => 'games', 'type' => 'folder', 'status' => 'published']);
        $ark = Page::create(['title' => 'Ark', 'slug' => 'ark', 'type' => 'folder', 'status' => 'published', 'parent_id' => $games->id]);
        Page::create(['title' => 'Ragnarok', 'slug' => 'ragnarok', 'type' => 'page', 'status' => 'published', 'parent_id' => $ark->id]);

        $response = $this->getJson('/api/v1/pages/games/ark/ragnarok');

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Ragnarok');
        $response->assertJsonPath('data.path', 'games/ark/ragnarok');
    }

    public function test_a_draft_page_404s_for_a_guest(): void
    {
        Page::create(['title' => 'Fjordur', 'slug' => 'fjordur', 'type' => 'page', 'status' => 'draft']);

        $this->getJson('/api/v1/pages/fjordur')->assertNotFound();
    }

    public function test_a_game_scoped_draft_page_returns_json_for_a_user_with_page_scope(): void
    {
        $palworld = Game::factory()->create();
        Page::create(['title' => 'Fjordur', 'slug' => 'fjordur', 'type' => 'page', 'status' => 'draft', 'game_id' => $palworld->id]);

        $role = Role::create(['name' => 'Drafter', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('game', $palworld->id, 'page'));
        $user = User::factory()->create();
        $user->assignRole('Drafter');

        $response = $this->actingAs($user)->getJson('/api/v1/pages/fjordur');

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Fjordur');
    }

    public function test_a_folder_is_not_directly_returnable(): void
    {
        Page::create(['title' => 'Games', 'slug' => 'games', 'type' => 'folder', 'status' => 'published']);

        $this->getJson('/api/v1/pages/games')->assertNotFound();
    }
}
