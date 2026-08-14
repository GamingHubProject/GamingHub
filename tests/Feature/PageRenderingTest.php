<?php

namespace Tests\Feature;

use App\Experience\ThemeResolver;
use App\Models\Game;
use App\Models\Page;
use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_page_renders_its_blocks(): void
    {
        $game = Game::factory()->create(['name' => 'Palworld', 'status' => 'enabled']);

        Page::create([
            'title' => 'Palworld Hub',
            'slug' => 'palworld-hub',
            'game_id' => $game->id,
            'status' => 'published',
            'blocks' => [
                ['type' => 'rich-text', 'config' => ['content' => '<p>Welcome</p>']],
                ['type' => 'games-list', 'config' => ['limit' => 5]],
            ],
        ]);

        $response = $this->get('/p/palworld-hub');

        $response->assertOk();
        $response->assertSee('Welcome', false);
        $response->assertSee('Palworld');
    }

    public function test_draft_page_is_not_publicly_reachable(): void
    {
        Page::create(['title' => 'Draft', 'slug' => 'draft-page', 'status' => 'draft']);

        $this->get('/p/draft-page')->assertNotFound();
    }

    public function test_theme_resolver_merges_platform_game_and_server_levels(): void
    {
        $game = Game::factory()->create();

        Theme::create([
            'name' => 'Platform',
            'level' => Theme::LEVEL_PLATFORM,
            'tokens' => ['color-primary' => '#000000', 'font-body' => 'Inter'],
            'is_default' => true,
        ]);

        Theme::create([
            'name' => 'Game override',
            'level' => Theme::LEVEL_GAME,
            'game_id' => $game->id,
            'tokens' => ['color-primary' => '#16a34a'],
        ]);

        $tokens = (new ThemeResolver)->resolve($game);

        $this->assertSame('#16a34a', $tokens['color-primary']);
        $this->assertSame('Inter', $tokens['font-body']);
    }
}
