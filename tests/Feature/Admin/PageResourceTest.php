<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Page;
use App\Models\PermissionScope;
use App\Models\User;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'edit_pages', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'see_drafts', 'guard_name' => 'web']);

        $adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(['edit_pages', 'see_drafts']);
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

    public function test_a_user_without_see_drafts_does_not_see_draft_pages_in_the_list(): void
    {
        $viewerRole = Role::create(['name' => 'Viewer', 'guard_name' => 'web']);
        $viewerRole->givePermissionTo('edit_pages');
        $user = User::factory()->create();
        $user->assignRole('Viewer');
        $this->actingAs($user);

        Page::create(['title' => 'Published Page', 'slug' => 'published-page', 'type' => 'page', 'status' => 'published']);
        Page::create(['title' => 'Draft Page', 'slug' => 'draft-page', 'type' => 'page', 'status' => 'draft']);

        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords(Page::where('slug', 'published-page')->get())
            ->assertCanNotSeeTableRecords(Page::where('slug', 'draft-page')->get());
    }

    public function test_a_role_scoped_to_one_game_only_sees_that_games_pages(): void
    {
        $palworld = Game::factory()->create(['name' => 'Palworld']);
        $ark = Game::factory()->create(['name' => 'Ark']);

        $scopedRole = Role::create(['name' => 'Palworld Editor', 'guard_name' => 'web']);
        $scopedRole->givePermissionTo(['edit_pages', 'see_drafts']);
        PermissionScope::create([
            'role_id' => $scopedRole->id,
            'permission' => 'edit_pages',
            'scope_type' => 'game',
            'scope_id' => $palworld->id,
        ]);

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

    public function test_an_unrestricted_role_sees_pages_for_every_game_and_global_pages(): void
    {
        $this->actingAsAdmin();
        $palworld = Game::factory()->create();

        $palworldPage = Page::create(['title' => 'Palworld Page', 'slug' => 'palworld-page', 'type' => 'page', 'status' => 'published', 'game_id' => $palworld->id]);
        $globalPage = Page::create(['title' => 'Global Page', 'slug' => 'global-page', 'type' => 'page', 'status' => 'published']);

        Livewire::test(ListPages::class)
            ->assertCanSeeTableRecords([$palworldPage, $globalPage]);
    }
}
