<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
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
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
    }

    public function test_can_list_pages(): void
    {
        Page::create(['title' => 'Home', 'slug' => 'home', 'status' => 'draft']);

        Livewire::test(ListPages::class)->assertSuccessful();
    }

    public function test_can_create_page_with_blocks(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'Palworld Hub',
                'slug' => 'palworld-hub',
                'status' => 'published',
                'blocks' => [
                    ['type' => 'rich-text', 'config' => ['content' => '<p>Hi</p>']],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('pages', ['slug' => 'palworld-hub', 'status' => 'published']);

        $page = Page::where('slug', 'palworld-hub')->firstOrFail();
        $this->assertSame('rich-text', $page->blocks[0]['type']);
    }
}
