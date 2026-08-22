<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\NavigationItemResource\Pages\EditNavigationItem;
use App\Filament\Resources\NavigationItemResource\Pages\ListNavigationItems;
use App\Models\NavigationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationItemResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
    }

    public function test_can_list_navigation_items(): void
    {
        Livewire::test(ListNavigationItems::class)->assertSuccessful();
    }

    public function test_can_edit_a_navigation_items_label_order_and_favorite(): void
    {
        $item = NavigationItem::where('key', 'games')->firstOrFail();

        Livewire::test(EditNavigationItem::class, ['record' => $item->id])
            ->fillForm(['label' => 'Game Titles', 'order' => 10, 'is_favorite' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $item->refresh();
        $this->assertSame('Game Titles', $item->label);
        $this->assertSame(10, $item->order);
        $this->assertTrue($item->is_favorite);
        $this->assertSame('games', $item->key, 'key must stay locked regardless of what the form submits');
    }

    public function test_the_key_field_cannot_be_changed_through_the_form(): void
    {
        $item = NavigationItem::where('key', 'games')->firstOrFail();

        Livewire::test(EditNavigationItem::class, ['record' => $item->id])
            ->fillForm(['key' => 'hacked'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('games', $item->fresh()->key);
    }

    public function test_there_is_no_create_route(): void
    {
        // No 'create' page registered in getPages() at all — locked
        // navigation items can't be created through the admin.
        $this->get('/admin/system/navigation-items/create')->assertNotFound();
    }
}
