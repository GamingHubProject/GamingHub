<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Permissions\ScopedPermissionName;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_root_level_published_page_renders(): void
    {
        Page::create(['title' => 'Community', 'slug' => 'community', 'type' => 'page', 'status' => 'published']);

        $response = $this->get('/community');

        $response->assertOk();
        $response->assertSee('Published: Community');
    }

    public function test_a_nested_published_page_resolves_through_its_folder_path(): void
    {
        $games = Page::create(['title' => 'Games', 'slug' => 'games', 'type' => 'folder', 'status' => 'published']);
        $ark = Page::create(['title' => 'Ark', 'slug' => 'ark', 'type' => 'folder', 'status' => 'published', 'parent_id' => $games->id]);
        Page::create(['title' => 'Ragnarok', 'slug' => 'ragnarok', 'type' => 'page', 'status' => 'published', 'parent_id' => $ark->id]);

        $response = $this->get('/games/ark/ragnarok');

        $response->assertOk();
        $response->assertSee('Published: Ragnarok');
    }

    public function test_a_draft_page_404s_for_a_guest(): void
    {
        Page::create(['title' => 'Fjordur', 'slug' => 'fjordur', 'type' => 'page', 'status' => 'draft']);

        $this->get('/fjordur')->assertNotFound();
    }

    public function test_a_global_draft_page_404s_for_a_user_without_page_scope(): void
    {
        Page::create(['title' => 'Fjordur', 'slug' => 'fjordur', 'type' => 'page', 'status' => 'draft']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/fjordur')->assertNotFound();
    }

    public function test_a_game_scoped_draft_page_renders_for_a_user_with_page_scope_on_that_game(): void
    {
        $palworld = Game::factory()->create();
        Page::create(['title' => 'Fjordur', 'slug' => 'fjordur', 'type' => 'page', 'status' => 'draft', 'game_id' => $palworld->id]);

        $role = Role::create(['name' => 'Drafter', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('game', $palworld->id, 'page'));
        $user = User::factory()->create();
        $user->assignRole('Drafter');

        $response = $this->actingAs($user)->get('/fjordur');

        $response->assertOk();
        $response->assertSee('Published: Fjordur');
    }

    public function test_a_game_scoped_draft_page_404s_for_a_user_with_page_scope_on_a_different_game(): void
    {
        $palworld = Game::factory()->create();
        $ark = Game::factory()->create();
        Page::create(['title' => 'Fjordur', 'slug' => 'fjordur', 'type' => 'page', 'status' => 'draft', 'game_id' => $palworld->id]);

        $role = Role::create(['name' => 'Ark Drafter', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('game', $ark->id, 'page'));
        $user = User::factory()->create();
        $user->assignRole('Ark Drafter');

        $this->actingAs($user)->get('/fjordur')->assertNotFound();
    }

    public function test_a_global_draft_page_renders_for_an_admin(): void
    {
        Page::create(['title' => 'Fjordur', 'slug' => 'fjordur', 'type' => 'page', 'status' => 'draft']);

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->get('/fjordur');

        $response->assertOk();
        $response->assertSee('Published: Fjordur');
    }

    public function test_a_folder_is_not_directly_renderable(): void
    {
        Page::create(['title' => 'Games', 'slug' => 'games', 'type' => 'folder', 'status' => 'published']);

        $this->get('/games')->assertNotFound();
    }

    public function test_an_unknown_path_segment_404s(): void
    {
        $games = Page::create(['title' => 'Games', 'slug' => 'games', 'type' => 'folder', 'status' => 'published']);
        Page::create(['title' => 'Ark', 'slug' => 'ark', 'type' => 'page', 'status' => 'published', 'parent_id' => $games->id]);

        $this->get('/games/nonexistent')->assertNotFound();
    }

    public function test_two_pages_in_different_folders_can_share_a_slug(): void
    {
        $ark = Page::create(['title' => 'Ark', 'slug' => 'ark', 'type' => 'folder', 'status' => 'published']);
        $palworld = Page::create(['title' => 'Palworld', 'slug' => 'palworld', 'type' => 'folder', 'status' => 'published']);
        Page::create(['title' => 'About (Ark)', 'slug' => 'about', 'type' => 'page', 'status' => 'published', 'parent_id' => $ark->id]);
        Page::create(['title' => 'About (Palworld)', 'slug' => 'about', 'type' => 'page', 'status' => 'published', 'parent_id' => $palworld->id]);

        $this->get('/ark/about')->assertOk()->assertSee('Published: About (Ark)');
        $this->get('/palworld/about')->assertOk()->assertSee('Published: About (Palworld)');
    }
}
