<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\NavigationLink;
use App\Models\Page;
use App\Models\User;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('Admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    private function tree(array $nodes): array
    {
        return ['tree' => $nodes];
    }

    // --- Public read -------------------------------------------------

    public function test_the_public_tree_is_empty_on_a_fresh_install(): void
    {
        $this->getJson('/api/v1/navigation')->assertOk()->assertJsonPath('data', []);
    }

    public function test_it_returns_links_in_position_order_with_children_nested(): void
    {
        $parent = NavigationLink::create(['type' => 'folder', 'label' => 'Community', 'position' => 1]);
        NavigationLink::create(['type' => 'link', 'label' => 'Discord', 'url' => 'https://discord.gg/x', 'parent_id' => $parent->id, 'position' => 0]);
        NavigationLink::create(['type' => 'page', 'label' => 'Home', 'target_type' => 'home', 'position' => 0]);

        $data = $this->getJson('/api/v1/navigation')->assertOk()->json('data');

        $this->assertSame(['Home', 'Community'], array_column($data, 'label'));
        $this->assertSame('Discord', $data[1]['children'][0]['label']);
    }

    public function test_a_page_link_resolves_to_a_url_rather_than_storing_one(): void
    {
        $game = Game::factory()->create(['slug' => 'phantom-galaxies']);
        NavigationLink::create(['type' => 'page', 'label' => 'Phantom', 'target_type' => 'game', 'target_id' => $game->id]);

        $this->getJson('/api/v1/navigation')->assertJsonPath('data.0.url', '/games/phantom-galaxies');
    }

    public function test_renaming_a_games_slug_moves_the_link_with_it(): void
    {
        // The whole reason a link stores what it points at instead of a URL.
        $game = Game::factory()->create(['slug' => 'old-slug']);
        NavigationLink::create(['type' => 'page', 'label' => 'Game', 'target_type' => 'game', 'target_id' => $game->id]);

        $game->update(['slug' => 'new-slug']);

        $this->getJson('/api/v1/navigation')->assertJsonPath('data.0.url', '/games/new-slug');
    }

    public function test_a_link_whose_target_was_deleted_is_dropped_rather_than_rendered_dead(): void
    {
        $game = Game::factory()->create();
        NavigationLink::create(['type' => 'page', 'label' => 'Gone', 'target_type' => 'game', 'target_id' => $game->id]);
        NavigationLink::create(['type' => 'page', 'label' => 'Home', 'target_type' => 'home']);
        $game->forceDelete();

        $data = $this->getJson('/api/v1/navigation')->json('data');

        $this->assertSame(['Home'], array_column($data, 'label'));
    }

    public function test_an_empty_folder_is_dropped_but_a_populated_one_is_kept(): void
    {
        $empty = NavigationLink::create(['type' => 'folder', 'label' => 'Empty', 'position' => 0]);
        $full = NavigationLink::create(['type' => 'folder', 'label' => 'Full', 'position' => 1]);
        NavigationLink::create(['type' => 'link', 'label' => 'Docs', 'url' => 'https://x.test', 'parent_id' => $full->id]);

        $data = $this->getJson('/api/v1/navigation')->json('data');

        $this->assertSame(['Full'], array_column($data, 'label'));
        $this->assertNull($data[0]['url'], 'a folder is a container, not a destination');
    }

    public function test_hidden_links_are_absent_from_the_public_tree(): void
    {
        NavigationLink::create(['type' => 'page', 'label' => 'Draft', 'target_type' => 'home', 'is_visible' => false]);

        $this->getJson('/api/v1/navigation')->assertJsonPath('data', []);
    }

    public function test_a_links_icon_is_exposed_as_a_url(): void
    {
        $asset = Asset::factory()->create(['url' => 'https://cdn.test/icon.png']);
        NavigationLink::create(['type' => 'page', 'label' => 'Home', 'target_type' => 'home', 'icon_asset_id' => $asset->id]);

        $this->getJson('/api/v1/navigation')->assertJsonPath('data.0.icon_url', 'https://cdn.test/icon.png');
    }

    // --- Editor read -------------------------------------------------

    public function test_the_editor_view_requires_an_admin(): void
    {
        $this->getJson('/api/v1/navigation/edit')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/navigation/edit')->assertForbidden();
    }

    public function test_the_editor_view_includes_hidden_links_the_public_tree_strips(): void
    {
        NavigationLink::create(['type' => 'page', 'label' => 'Draft', 'target_type' => 'home', 'is_visible' => false]);

        $this->actingAs($this->admin())->getJson('/api/v1/navigation/edit')
            ->assertOk()
            ->assertJsonPath('tree.0.label', 'Draft')
            ->assertJsonPath('tree.0.is_visible', false);
    }

    public function test_the_editor_view_bundles_the_pickable_targets(): void
    {
        // One request, so the editor can render a target picker without a
        // second round trip.
        Game::factory()->create(['name' => 'Phantom Galaxies']);

        $groups = $this->actingAs($this->admin())->getJson('/api/v1/navigation/edit')->json('targets');

        $names = array_column($groups, 'group');
        $this->assertContains('Pages', $names);
        $this->assertContains('Games', $names);
    }

    public function test_the_target_list_covers_static_routes_web_tree_pages_games_and_servers(): void
    {
        $game = Game::factory()->create(['slug' => 'ark', 'name' => 'Ark']);
        \GamingHub\Core\Models\Server::factory()->create(['game_id' => $game->id, 'name' => 'Ragnarok']);
        Page::create(['title' => 'About', 'slug' => 'about']);

        $groups = collect($this->actingAs($this->admin())->getJson('/api/v1/navigation/edit')->json('targets'))
            ->keyBy('group');

        $this->assertSame(['home', 'games', 'dashboard'], array_column($groups['Pages']['options'], 'target_type'));
        $this->assertSame('/about', $groups['Site pages']['options'][0]['url']);
        $this->assertSame('/games/ark', $groups['Games']['options'][0]['url']);
        $this->assertStringContainsString('/games/ark/servers/', $groups['Servers']['options'][0]['url']);
    }

    // --- Whole-tree write --------------------------------------------

    public function test_replacing_the_tree_requires_an_admin(): void
    {
        $this->putJson('/api/v1/navigation/tree', $this->tree([]))->assertUnauthorized();
        $this->actingAs(User::factory()->create())
            ->putJson('/api/v1/navigation/tree', $this->tree([]))
            ->assertForbidden();
    }

    public function test_it_creates_a_nested_tree_from_scratch(): void
    {
        $this->actingAs($this->admin())->putJson('/api/v1/navigation/tree', $this->tree([
            ['type' => 'page', 'label' => 'Home', 'target_type' => 'home'],
            ['type' => 'folder', 'label' => 'Community', 'children' => [
                ['type' => 'link', 'label' => 'Discord', 'url' => 'https://discord.gg/x'],
            ]],
        ]))->assertOk();

        $this->assertSame(3, NavigationLink::count());
        $folder = NavigationLink::where('label', 'Community')->firstOrFail();
        $this->assertSame('Discord', NavigationLink::where('parent_id', $folder->id)->firstOrFail()->label);
    }

    public function test_it_renumbers_positions_from_the_order_it_was_given(): void
    {
        $this->actingAs($this->admin())->putJson('/api/v1/navigation/tree', $this->tree([
            ['type' => 'page', 'label' => 'First', 'target_type' => 'home'],
            ['type' => 'page', 'label' => 'Second', 'target_type' => 'games'],
        ]))->assertOk();

        $this->assertSame(0, NavigationLink::where('label', 'First')->firstOrFail()->position);
        $this->assertSame(1, NavigationLink::where('label', 'Second')->firstOrFail()->position);
    }

    public function test_an_existing_link_keeps_its_id_when_it_only_moves(): void
    {
        // Ids surviving a reorder is what lets a link keep its icon and
        // stay the same row for anything that references it.
        $a = NavigationLink::create(['type' => 'page', 'label' => 'A', 'target_type' => 'home', 'position' => 0]);
        $b = NavigationLink::create(['type' => 'page', 'label' => 'B', 'target_type' => 'games', 'position' => 1]);

        $this->actingAs($this->admin())->putJson('/api/v1/navigation/tree', $this->tree([
            ['id' => $b->id, 'type' => 'page', 'label' => 'B', 'target_type' => 'games'],
            ['id' => $a->id, 'type' => 'page', 'label' => 'A', 'target_type' => 'home'],
        ]))->assertOk();

        $this->assertSame(0, $b->refresh()->position);
        $this->assertSame(1, $a->refresh()->position);
        $this->assertSame(2, NavigationLink::count());
    }

    public function test_a_link_can_be_dragged_into_a_folder_without_being_recreated(): void
    {
        $folder = NavigationLink::create(['type' => 'folder', 'label' => 'Community', 'position' => 0]);
        $link = NavigationLink::create(['type' => 'link', 'label' => 'Discord', 'url' => 'https://x.test', 'position' => 1]);

        $this->actingAs($this->admin())->putJson('/api/v1/navigation/tree', $this->tree([
            ['id' => $folder->id, 'type' => 'folder', 'label' => 'Community', 'children' => [
                ['id' => $link->id, 'type' => 'link', 'label' => 'Discord', 'url' => 'https://x.test'],
            ]],
        ]))->assertOk();

        $this->assertSame($folder->id, $link->refresh()->parent_id);
        $this->assertSame(2, NavigationLink::count());
    }

    public function test_a_link_left_out_of_the_payload_is_deleted(): void
    {
        // What makes a removal in the editor stick.
        $keep = NavigationLink::create(['type' => 'page', 'label' => 'Keep', 'target_type' => 'home']);
        NavigationLink::create(['type' => 'page', 'label' => 'Drop', 'target_type' => 'games']);

        $this->actingAs($this->admin())->putJson('/api/v1/navigation/tree', $this->tree([
            ['id' => $keep->id, 'type' => 'page', 'label' => 'Keep', 'target_type' => 'home'],
        ]))->assertOk();

        $this->assertSame(['Keep'], NavigationLink::pluck('label')->all());
    }

    public function test_an_empty_payload_clears_the_whole_tree(): void
    {
        NavigationLink::create(['type' => 'page', 'label' => 'Home', 'target_type' => 'home']);

        $this->actingAs($this->admin())->putJson('/api/v1/navigation/tree', $this->tree([]))->assertOk();

        $this->assertSame(0, NavigationLink::count());
    }

    public function test_nesting_deeper_than_the_cap_is_flattened_rather_than_rejected(): void
    {
        // A client sending something too deep gets a valid tree back, not
        // a 422 in the middle of a drag.
        $this->actingAs($this->admin())->putJson('/api/v1/navigation/tree', $this->tree([
            ['type' => 'folder', 'label' => 'L1', 'children' => [
                ['type' => 'folder', 'label' => 'L2', 'children' => [
                    ['type' => 'link', 'label' => 'L3', 'url' => 'https://x.test'],
                ]],
            ]],
        ]))->assertOk();

        $this->assertNull(NavigationLink::where('label', 'L3')->first());
        $this->assertSame(2, NavigationLink::count());
    }

    public function test_it_rejects_an_unknown_link_type(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/v1/navigation/tree', $this->tree([['type' => 'wormhole', 'label' => 'Nope']]))
            ->assertJsonValidationErrors('tree.0.type');
    }

    public function test_it_rejects_a_link_with_no_label(): void
    {
        $this->actingAs($this->admin())
            ->putJson('/api/v1/navigation/tree', $this->tree([['type' => 'link', 'url' => 'https://x.test']]))
            ->assertJsonValidationErrors('tree.0.label');
    }

    public function test_a_failed_write_leaves_the_existing_tree_untouched(): void
    {
        NavigationLink::create(['type' => 'page', 'label' => 'Existing', 'target_type' => 'home']);

        $this->actingAs($this->admin())
            ->putJson('/api/v1/navigation/tree', $this->tree([['type' => 'wormhole', 'label' => 'Nope']]))
            ->assertStatus(422);

        $this->assertSame(['Existing'], NavigationLink::pluck('label')->all());
    }

    public function test_the_write_returns_the_editor_view_of_what_it_saved(): void
    {
        $this->actingAs($this->admin())->putJson('/api/v1/navigation/tree', $this->tree([
            ['type' => 'page', 'label' => 'Home', 'target_type' => 'home'],
        ]))->assertOk()->assertJsonPath('tree.0.label', 'Home')->assertJsonStructure(['tree', 'targets']);
    }

    public function test_deleting_a_folder_takes_its_children_with_it(): void
    {
        $folder = NavigationLink::create(['type' => 'folder', 'label' => 'Community']);
        NavigationLink::create(['type' => 'link', 'label' => 'Discord', 'url' => 'https://x.test', 'parent_id' => $folder->id]);

        $folder->delete();

        $this->assertSame(0, NavigationLink::count());
    }
}
