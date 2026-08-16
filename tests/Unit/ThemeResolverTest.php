<?php

namespace Tests\Unit;

use App\Experience\ThemeResolver;
use App\Models\Theme;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_resolver_merges_platform_and_game_levels(): void
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
