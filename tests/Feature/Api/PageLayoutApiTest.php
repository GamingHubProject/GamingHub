<?php

namespace Tests\Feature\Api;

use App\Models\PageLayout;
use App\Models\PageLayoutWidget;
use App\Models\User;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageLayoutApiTest extends TestCase
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

    // --- Server subject ---

    public function test_server_show_auto_creates_an_empty_layout_for_a_server_with_none_yet(): void
    {
        $server = Server::factory()->create();

        $response = $this->getJson("/api/v1/servers/{$server->id}/layout");

        $response->assertOk();
        $response->assertJsonPath('data.subject_type', 'server');
        $response->assertJsonPath('data.subject_id', $server->id);
        $response->assertJsonPath('data.widgets', []);
        $this->assertDatabaseHas('page_layouts', ['subject_type' => 'server', 'subject_id' => $server->id]);
    }

    public function test_server_show_is_public_no_auth_required(): void
    {
        $server = Server::factory()->create();

        $this->getJson("/api/v1/servers/{$server->id}/layout")->assertOk();
    }

    public function test_server_show_returns_existing_widgets(): void
    {
        $server = Server::factory()->create();
        $layout = PageLayout::create(['subject_type' => 'server', 'subject_id' => $server->id]);
        PageLayoutWidget::create([
            'page_layout_id' => $layout->id,
            'widget_type' => 'server-status',
            'position_x' => 0,
            'position_y' => 0,
            'width' => 3,
            'height' => 2,
        ]);

        $response = $this->getJson("/api/v1/servers/{$server->id}/layout");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.widgets');
        $response->assertJsonPath('data.widgets.0.widget_type', 'server-status');
    }

    public function test_server_show_does_not_duplicate_the_layout_row_on_repeat_requests(): void
    {
        $server = Server::factory()->create();

        $this->getJson("/api/v1/servers/{$server->id}/layout")->assertOk();
        $this->getJson("/api/v1/servers/{$server->id}/layout")->assertOk();

        $this->assertSame(1, PageLayout::where('subject_type', 'server')->where('subject_id', $server->id)->count());
    }

    // --- Game subject ---

    public function test_game_show_auto_creates_an_empty_layout_keyed_by_the_games_id_not_its_slug(): void
    {
        $game = Game::factory()->create(['slug' => 'palworld', 'status' => 'enabled']);

        $response = $this->getJson('/api/v1/games/palworld/layout');

        $response->assertOk();
        $response->assertJsonPath('data.subject_type', 'game');
        $response->assertJsonPath('data.subject_id', $game->id);
        $this->assertDatabaseHas('page_layouts', ['subject_type' => 'game', 'subject_id' => $game->id]);
    }

    public function test_game_show_404s_for_an_unknown_slug(): void
    {
        $this->getJson('/api/v1/games/no-such-game/layout')->assertNotFound();
    }

    public function test_server_and_game_layouts_never_collide_even_with_the_same_numeric_id(): void
    {
        $server = Server::factory()->create();
        $game = Game::factory()->create(['id' => $server->id, 'slug' => 'same-id-game', 'status' => 'enabled']);

        $this->getJson("/api/v1/servers/{$server->id}/layout")->assertOk();
        $this->getJson('/api/v1/games/same-id-game/layout')->assertOk();

        $this->assertSame(2, PageLayout::where('subject_id', $server->id)->count());
    }

    // --- Home subject ---

    public function test_home_show_auto_creates_the_singleton_layout(): void
    {
        $response = $this->getJson('/api/v1/home/layout');

        $response->assertOk();
        $response->assertJsonPath('data.subject_type', 'home');
        $response->assertJsonPath('data.subject_id', PageLayout::SINGLETON_SUBJECT_ID);
        $this->assertDatabaseHas('page_layouts', ['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
    }

    public function test_home_show_is_a_singleton_across_repeat_requests(): void
    {
        $this->getJson('/api/v1/home/layout')->assertOk();
        $this->getJson('/api/v1/home/layout')->assertOk();

        $this->assertSame(1, PageLayout::where('subject_type', 'home')->count());
    }

    public function test_home_show_seeds_a_default_game_card_widget_on_first_creation(): void
    {
        $response = $this->getJson('/api/v1/home/layout');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.widgets');
        $response->assertJsonPath('data.widgets.0.widget_type', 'game-card');
        $response->assertJsonPath('data.widgets.0.config.mode', 'all');
    }

    public function test_home_show_does_not_reseed_a_layout_an_admin_has_already_emptied(): void
    {
        $this->getJson('/api/v1/home/layout')->assertOk();
        $layout = PageLayout::where('subject_type', 'home')->first();
        $layout->widgets()->delete();

        $response = $this->getJson('/api/v1/home/layout');

        $response->assertJsonCount(0, 'data.widgets');
    }

    // --- Games list subject ---

    public function test_games_list_show_auto_creates_the_singleton_layout_seeded_with_a_game_card(): void
    {
        $response = $this->getJson('/api/v1/games-list/layout');

        $response->assertOk();
        $response->assertJsonPath('data.subject_type', 'games-list');
        $response->assertJsonPath('data.subject_id', PageLayout::SINGLETON_SUBJECT_ID);
        $response->assertJsonCount(1, 'data.widgets');
        $response->assertJsonPath('data.widgets.0.widget_type', 'game-card');
    }

    public function test_games_list_show_is_a_singleton_across_repeat_requests(): void
    {
        $this->getJson('/api/v1/games-list/layout')->assertOk();
        $this->getJson('/api/v1/games-list/layout')->assertOk();

        $this->assertSame(1, PageLayout::where('subject_type', 'games-list')->count());
    }

    public function test_home_and_games_list_singletons_never_collide(): void
    {
        $this->getJson('/api/v1/home/layout')->assertOk();
        $this->getJson('/api/v1/games-list/layout')->assertOk();

        $this->assertSame(2, PageLayout::where('subject_id', PageLayout::SINGLETON_SUBJECT_ID)->count());
    }

    // --- Widget writes (subject-agnostic) ---

    public function test_store_requires_authentication(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);

        $this->postJson("/api/v1/page-layouts/{$layout->id}/widgets", ['widget_type' => 'server-status'])
            ->assertUnauthorized();
    }

    public function test_store_requires_the_admin_role(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/v1/page-layouts/{$layout->id}/widgets", ['widget_type' => 'server-status'])
            ->assertForbidden();
    }

    public function test_store_creates_a_widget_on_a_game_layout(): void
    {
        $game = Game::factory()->create(['slug' => 'palworld', 'status' => 'enabled']);
        $this->getJson('/api/v1/games/palworld/layout')->assertOk();
        $layout = PageLayout::where('subject_type', 'game')->where('subject_id', $game->id)->first();

        $response = $this->actingAs($this->admin())->postJson("/api/v1/page-layouts/{$layout->id}/widgets", [
            'widget_type' => 'picture',
            'position_x' => 0,
            'position_y' => 0,
            'width' => 12,
            'height' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.widget_type', 'picture');
        $this->assertDatabaseHas('page_layout_widgets', ['page_layout_id' => $layout->id, 'widget_type' => 'picture']);
    }

    public function test_store_falls_back_to_default_position_and_size_when_omitted(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);

        $response = $this->actingAs($this->admin())->postJson("/api/v1/page-layouts/{$layout->id}/widgets", [
            'widget_type' => 'server-status',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.position_x', 0);
        $response->assertJsonPath('data.position_y', 0);
        $response->assertJsonPath('data.width', 6);
        $response->assertJsonPath('data.height', 4);
    }

    public function test_update_requires_the_admin_role(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $widget = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'server-status']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson("/api/v1/page-layout-widgets/{$widget->id}", ['width' => 3])
            ->assertForbidden();
    }

    public function test_update_persists_a_new_position_and_size(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $widget = PageLayoutWidget::create([
            'page_layout_id' => $layout->id,
            'widget_type' => 'server-metrics',
            'position_x' => 0,
            'position_y' => 0,
            'width' => 6,
            'height' => 4,
        ]);

        $response = $this->actingAs($this->admin())->patchJson("/api/v1/page-layout-widgets/{$widget->id}", [
            'position_x' => 3,
            'position_y' => 4,
            'width' => 4,
            'height' => 3,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('page_layout_widgets', [
            'id' => $widget->id,
            'position_x' => 3,
            'position_y' => 4,
            'width' => 4,
            'height' => 3,
        ]);
    }

    public function test_update_persists_a_config_toggle(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $widget = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'server-status']);

        $response = $this->actingAs($this->admin())->patchJson("/api/v1/page-layout-widgets/{$widget->id}", [
            'config' => ['show_node' => true],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.config.show_node', true);
        $this->assertSame(['show_node' => true], $widget->fresh()->config);
    }

    public function test_destroy_requires_the_admin_role(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $widget = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'server-allocations']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson("/api/v1/page-layout-widgets/{$widget->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('page_layout_widgets', ['id' => $widget->id]);
    }

    public function test_destroy_removes_the_widget(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $widget = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'server-allocations']);

        $response = $this->actingAs($this->admin())->deleteJson("/api/v1/page-layout-widgets/{$widget->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('page_layout_widgets', ['id' => $widget->id]);
    }

    // --- Per-page font override ---

    public function test_update_requires_the_admin_role_for_font(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson("/api/v1/page-layouts/{$layout->id}", ['font_asset_id' => 1])
            ->assertForbidden();
    }

    public function test_update_sets_the_pages_font_override(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $asset = \App\Models\Asset::factory()->create();

        $response = $this->actingAs($this->admin())->patchJson("/api/v1/page-layouts/{$layout->id}", [
            'font_asset_id' => $asset->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.font_asset_id', $asset->id);
        $this->assertSame($asset->id, $layout->fresh()->font_asset_id);
    }

    public function test_update_clears_the_font_override_back_to_sync_with_global(): void
    {
        $asset = \App\Models\Asset::factory()->create();
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID, 'font_asset_id' => $asset->id]);

        $response = $this->actingAs($this->admin())->patchJson("/api/v1/page-layouts/{$layout->id}", [
            'font_asset_id' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.font_asset_id', null);
        $this->assertNull($layout->fresh()->font_asset_id);
    }

    // --- Group widgets ---

    public function test_store_accepts_a_group_widget_id_pointing_at_a_real_group_on_the_same_layout(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $group = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'group']);

        $response = $this->actingAs($this->admin())->postJson("/api/v1/page-layouts/{$layout->id}/widgets", [
            'widget_type' => 'server-status',
            'group_widget_id' => $group->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.group_widget_id', $group->id);
    }

    public function test_store_rejects_a_group_widget_id_pointing_at_a_non_group_widget(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $notAGroup = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'picture']);

        $response = $this->actingAs($this->admin())->postJson("/api/v1/page-layouts/{$layout->id}/widgets", [
            'widget_type' => 'server-status',
            'group_widget_id' => $notAGroup->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_a_group_widget_id_from_a_different_layout(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $otherLayout = PageLayout::create(['subject_type' => 'games-list', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $groupOnOtherLayout = PageLayoutWidget::create(['page_layout_id' => $otherLayout->id, 'widget_type' => 'group']);

        $response = $this->actingAs($this->admin())->postJson("/api/v1/page-layouts/{$layout->id}/widgets", [
            'widget_type' => 'server-status',
            'group_widget_id' => $groupOnOtherLayout->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_a_group_widget_itself_being_given_a_group_widget_id(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $outerGroup = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'group']);

        $response = $this->actingAs($this->admin())->postJson("/api/v1/page-layouts/{$layout->id}/widgets", [
            'widget_type' => 'group',
            'group_widget_id' => $outerGroup->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_a_group_widget_id_pointing_at_an_already_nested_group(): void
    {
        // Bypasses the API to force the illegal state directly via the
        // model — the API itself should never let this exist, but the
        // guard on the *reading* side (this test) has to hold regardless
        // of how such a row came to exist.
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $outerGroup = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'group']);
        $nestedGroup = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'group', 'group_widget_id' => $outerGroup->id]);

        $response = $this->actingAs($this->admin())->postJson("/api/v1/page-layouts/{$layout->id}/widgets", [
            'widget_type' => 'server-status',
            'group_widget_id' => $nestedGroup->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_update_can_reparent_a_widget_into_a_group_and_reposition_it(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $group = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'group', 'position_x' => 2, 'position_y' => 3, 'width' => 6, 'height' => 4]);
        $widget = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'server-status', 'position_x' => 2, 'position_y' => 3]);

        $response = $this->actingAs($this->admin())->patchJson("/api/v1/page-layout-widgets/{$widget->id}", [
            'group_widget_id' => $group->id,
            'position_x' => 0,
            'position_y' => 0,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('page_layout_widgets', ['id' => $widget->id, 'group_widget_id' => $group->id, 'position_x' => 0, 'position_y' => 0]);
    }

    public function test_update_rejects_setting_a_group_widget_id_on_a_group_widget_when_widget_type_is_not_present_in_the_request(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $outerGroup = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'group']);
        $innerGroup = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'group']);

        // widget_type isn't in this request at all — the resolved-type
        // check has to fall back to the row's *existing* widget_type
        // ('group'), not silently pass because the field was omitted.
        $response = $this->actingAs($this->admin())->patchJson("/api/v1/page-layout-widgets/{$innerGroup->id}", [
            'group_widget_id' => $outerGroup->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_destroy_cascades_to_the_groups_children(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $group = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'group']);
        $child = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'server-status', 'group_widget_id' => $group->id]);

        $response = $this->actingAs($this->admin())->deleteJson("/api/v1/page-layout-widgets/{$group->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('page_layout_widgets', ['id' => $group->id]);
        $this->assertDatabaseMissing('page_layout_widgets', ['id' => $child->id]);
    }

    public function test_destroy_auto_deletes_a_group_left_with_no_remaining_children(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $group = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'group']);
        $onlyChild = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'server-status', 'group_widget_id' => $group->id]);

        $response = $this->actingAs($this->admin())->deleteJson("/api/v1/page-layout-widgets/{$onlyChild->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('page_layout_widgets', ['id' => $onlyChild->id]);
        $this->assertDatabaseMissing('page_layout_widgets', ['id' => $group->id]);
    }

    public function test_destroy_does_not_delete_a_group_that_still_has_other_children(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $group = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'group']);
        $childA = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'server-status', 'group_widget_id' => $group->id]);
        $childB = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'server-name', 'group_widget_id' => $group->id]);

        $response = $this->actingAs($this->admin())->deleteJson("/api/v1/page-layout-widgets/{$childA->id}");

        $response->assertNoContent();
        $this->assertDatabaseHas('page_layout_widgets', ['id' => $group->id]);
        $this->assertDatabaseHas('page_layout_widgets', ['id' => $childB->id]);
    }
}
