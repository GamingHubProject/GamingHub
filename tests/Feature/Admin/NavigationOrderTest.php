<?php

namespace Tests\Feature\Admin;

use App\Models\NavigationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): User
    {
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);

        return $admin;
    }

    public function test_the_sidebar_renders_groups_in_seeded_default_order(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSeeInOrder(['Capabilities', 'Extensions', 'Games', 'Experience', 'Servers', 'Administration', 'Basic Settings']);
    }

    public function test_reordering_a_navigation_item_changes_the_rendered_sidebar_order(): void
    {
        $this->actingAsAdmin();

        NavigationItem::where('key', 'administration')->update(['order' => 0]);

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSeeInOrder(['Administration', 'Capabilities', 'Extensions', 'Games', 'Experience', 'Servers', 'Basic Settings']);
    }

    public function test_a_favorited_group_sorts_before_non_favorites_regardless_of_order_value(): void
    {
        $this->actingAsAdmin();

        NavigationItem::where('key', 'servers')->update(['is_favorite' => true]);

        $response = $this->get('/admin');

        $response->assertOk();
        // 'servers' has order=5, higher than several non-favorites, but its
        // favorite flag must still put it first.
        $response->assertSeeInOrder(['★ Servers', 'Capabilities', 'Extensions', 'Games', 'Experience', 'Administration', 'Basic Settings']);
    }

    public function test_renaming_a_labels_display_text_does_not_orphan_its_resources(): void
    {
        $this->actingAsAdmin();

        NavigationItem::where('key', 'games')->update(['label' => 'Game Titles']);

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('Game Titles');
        // The renamed group must still contain its actual resource links,
        // not just an empty header — Games is still reachable under its
        // new label.
        $response->assertSeeInOrder(['Game Titles', 'Games', 'Instances', 'Maps']);
    }

    public function test_navigation_items_sort_helper_puts_favorites_first(): void
    {
        NavigationItem::query()->delete();
        NavigationItem::create(['key' => 'a', 'label' => 'A', 'order' => 1, 'is_favorite' => false]);
        NavigationItem::create(['key' => 'b', 'label' => 'B', 'order' => 2, 'is_favorite' => true]);
        NavigationItem::create(['key' => 'c', 'label' => 'C', 'order' => 0, 'is_favorite' => false]);

        $ordered = NavigationItem::inSidebarOrder()->pluck('key')->all();

        $this->assertSame(['b', 'c', 'a'], $ordered);
    }
}
