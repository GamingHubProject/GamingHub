<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use App\Permissions\ScopedPermissionName;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    }

    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);

        return $admin;
    }

    public function test_can_list_pages(): void
    {
        $this->actingAsAdmin();
        Page::create(['title' => 'Home', 'slug' => 'home', 'type' => 'page', 'status' => 'draft']);

        Livewire::test(ListPages::class)->assertSuccessful();
    }

    public function test_can_create_a_folder(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Games', 'slug' => 'games', 'type' => 'folder', 'status' => 'published'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pages', ['slug' => 'games', 'type' => 'folder']);
    }

    public function test_can_create_a_nested_page_with_content(): void
    {
        $this->actingAsAdmin();
        $folder = Page::create(['title' => 'Games', 'slug' => 'games', 'type' => 'folder', 'status' => 'published']);

        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'Palworld Hub',
                'slug' => 'palworld-hub',
                'type' => 'page',
                'status' => 'published',
                'parent_id' => $folder->id,
                'content' => 'Welcome to Palworld',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::where('slug', 'palworld-hub')->firstOrFail();
        $this->assertSame($folder->id, $page->parent_id);
        $this->assertSame('Welcome to Palworld', $page->content);
    }

    public function test_can_move_a_page_to_a_different_folder(): void
    {
        $this->actingAsAdmin();
        $oldFolder = Page::create(['title' => 'Old', 'slug' => 'old', 'type' => 'folder', 'status' => 'published']);
        $newFolder = Page::create(['title' => 'New', 'slug' => 'new', 'type' => 'folder', 'status' => 'published']);
        $page = Page::create(['title' => 'Moved', 'slug' => 'moved', 'type' => 'page', 'status' => 'published', 'parent_id' => $oldFolder->id]);

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->fillForm(['parent_id' => $newFolder->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($newFolder->id, $page->fresh()->parent_id);
    }

    public function test_deleting_a_page_soft_deletes_it(): void
    {
        $this->actingAsAdmin();
        $page = Page::create(['title' => 'Gone', 'slug' => 'gone', 'type' => 'page', 'status' => 'published']);

        $page->delete();

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
    }

    public function test_page_scope_grants_both_published_and_draft_visibility_for_that_game(): void
    {
        $palworld = Game::factory()->create();

        $role = Role::create(['name' => 'Palworld Editor', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('game', $palworld->id, 'page'));
        $user = User::factory()->create();
        $user->assignRole('Palworld Editor');
        $this->actingAs($user);

        $published = Page::create(['title' => 'Published Page', 'slug' => 'published-page', 'type' => 'page', 'status' => 'published', 'game_id' => $palworld->id]);
        $draft = Page::create(['title' => 'Draft Page', 'slug' => 'draft-page', 'type' => 'page', 'status' => 'draft', 'game_id' => $palworld->id]);

        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords([$published, $draft]);
    }

    public function test_without_page_scope_for_a_game_neither_published_nor_draft_pages_are_visible(): void
    {
        $palworld = Game::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user);

        $published = Page::create(['title' => 'Published Page', 'slug' => 'published-page', 'type' => 'page', 'status' => 'published', 'game_id' => $palworld->id]);
        $draft = Page::create(['title' => 'Draft Page', 'slug' => 'draft-page', 'type' => 'page', 'status' => 'draft', 'game_id' => $palworld->id]);

        Livewire::test(ListPages::class)
            ->assertCanNotSeeTableRecords([$published, $draft]);
    }

    public function test_a_role_scoped_to_one_game_only_sees_that_games_pages(): void
    {
        $palworld = Game::factory()->create(['name' => 'Palworld']);
        $ark = Game::factory()->create(['name' => 'Ark']);

        $scopedRole = Role::create(['name' => 'Palworld Editor', 'guard_name' => 'web']);
        $scopedRole->givePermissionTo(ScopedPermissionName::for('game', $palworld->id, 'page'));

        $user = User::factory()->create();
        $user->assignRole('Palworld Editor');
        $this->actingAs($user);

        $palworldPage = Page::create(['title' => 'Palworld Page', 'slug' => 'palworld-page', 'type' => 'page', 'status' => 'published', 'game_id' => $palworld->id]);
        $arkPage = Page::create(['title' => 'Ark Page', 'slug' => 'ark-page', 'type' => 'page', 'status' => 'published', 'game_id' => $ark->id]);
        $globalPage = Page::create(['title' => 'Global Page', 'slug' => 'global-page', 'type' => 'page', 'status' => 'published']);

        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords([$palworldPage])
            ->assertCanNotSeeTableRecords([$arkPage, $globalPage]);
    }

    public function test_a_global_page_is_visible_only_to_admin(): void
    {
        $palworld = Game::factory()->create();
        $role = Role::create(['name' => 'Palworld Editor', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('game', $palworld->id, 'page'));
        $user = User::factory()->create();
        $user->assignRole('Palworld Editor');
        $this->actingAs($user);

        $globalPage = Page::create(['title' => 'Global Page', 'slug' => 'global-page', 'type' => 'page', 'status' => 'published']);

        Livewire::test(ListPages::class)
            ->assertCanNotSeeTableRecords([$globalPage]);
    }

    public function test_an_admin_sees_pages_for_every_game_and_global_pages(): void
    {
        $this->actingAsAdmin();
        $palworld = Game::factory()->create();

        $palworldPage = Page::create(['title' => 'Palworld Page', 'slug' => 'palworld-page', 'type' => 'page', 'status' => 'published', 'game_id' => $palworld->id]);
        $globalPage = Page::create(['title' => 'Global Page', 'slug' => 'global-page', 'type' => 'page', 'status' => 'published']);

        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords([$palworldPage, $globalPage]);
    }
}
