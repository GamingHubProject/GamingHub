<?php

namespace Tests\Unit;

use App\Experience\ThemeResolver;
use App\Models\ThemeAssignment;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithThemes;
use Tests\TestCase;

class ThemeResolverTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithThemes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeThemeDisk();
        // The migration assigns its migrated theme to the platform level;
        // these tests set up their own scopes from scratch.
        ThemeAssignment::query()->delete();
    }

    private function resolver(): ThemeResolver
    {
        return new ThemeResolver;
    }

    public function test_merges_a_game_theme_over_the_platform_theme(): void
    {
        $game = Game::factory()->create();
        $this->makeTheme('Platform', ['tokens' => ['accent' => '#000000', 'surface' => '#ffffff']], ThemeAssignment::LEVEL_PLATFORM);
        $this->makeTheme('Game', ['tokens' => ['accent' => '#16a34a']], ThemeAssignment::LEVEL_GAME, gameId: $game->id);

        $tokens = $this->resolver()->resolve($game);

        // The game theme wins where it sets a token, and inherits where it doesn't.
        $this->assertSame('#16a34a', $tokens['accent']);
        $this->assertSame('#ffffff', $tokens['surface']);
    }

    public function test_a_server_theme_wins_over_both_levels_beneath_it(): void
    {
        $game = Game::factory()->create();
        $server = \GamingHub\Core\Models\Server::factory()->create(['game_id' => $game->id]);
        $this->makeTheme('Platform', ['tokens' => ['accent' => '#000000']], ThemeAssignment::LEVEL_PLATFORM);
        $this->makeTheme('Game', ['tokens' => ['accent' => '#16a34a']], ThemeAssignment::LEVEL_GAME, gameId: $game->id);
        $this->makeTheme('Server', ['tokens' => ['accent' => '#dc2626']], ThemeAssignment::LEVEL_SERVER, serverId: $server->id);

        $this->assertSame('#dc2626', $this->resolver()->resolve($game, $server)['accent']);
    }

    public function test_returns_nothing_when_no_theme_is_assigned_anywhere(): void
    {
        $this->assertSame([], $this->resolver()->resolve());
        $this->assertNull($this->resolver()->effectiveTheme());
    }

    public function test_the_effective_theme_is_the_most_specific_one_in_scope(): void
    {
        $game = Game::factory()->create();
        $this->makeTheme('Platform', [], ThemeAssignment::LEVEL_PLATFORM);
        $gameTheme = $this->makeTheme('Game', [], ThemeAssignment::LEVEL_GAME, gameId: $game->id);

        $this->assertSame($gameTheme->id, $this->resolver()->effectiveTheme($game)?->id);
    }

    public function test_a_game_with_no_theme_of_its_own_falls_through_to_the_platform(): void
    {
        $game = Game::factory()->create();
        $platform = $this->makeTheme('Platform', ['tokens' => ['accent' => '#000000']], ThemeAssignment::LEVEL_PLATFORM);

        $this->assertSame($platform->id, $this->resolver()->effectiveTheme($game)?->id);
    }

    public function test_one_theme_can_be_assigned_to_several_games_at_once(): void
    {
        // The old shape couldn't express this — scope lived on the theme
        // row, so two games meant two duplicated themes to keep in sync.
        $a = Game::factory()->create();
        $b = Game::factory()->create();
        $shared = $this->makeTheme('Shared', ['tokens' => ['accent' => '#7c3aed']]);
        ThemeAssignment::assign(ThemeAssignment::LEVEL_GAME, $shared->id, $a->id);
        ThemeAssignment::assign(ThemeAssignment::LEVEL_GAME, $shared->id, $b->id);

        $this->assertSame('#7c3aed', $this->resolver()->resolve($a)['accent']);
        $this->assertSame('#7c3aed', $this->resolver()->resolve($b)['accent']);
    }

    public function test_assigning_a_scope_replaces_whatever_was_there(): void
    {
        $first = $this->makeTheme('First', [], ThemeAssignment::LEVEL_PLATFORM);
        $second = $this->makeTheme('Second');

        ThemeAssignment::assign(ThemeAssignment::LEVEL_PLATFORM, $second->id);

        $this->assertSame(1, ThemeAssignment::where('level', 'platform')->count());
        $this->assertSame($second->id, $this->resolver()->effectiveTheme()?->id);
        $this->assertNotSame($first->id, $this->resolver()->effectiveTheme()?->id);
    }

    public function test_widget_style_and_site_chrome_come_from_the_effective_theme(): void
    {
        $theme = $this->makeTheme('Platform', [
            'widget_style' => ['border_radius' => 12],
            'header_transparent' => true,
        ], ThemeAssignment::LEVEL_PLATFORM);

        $this->assertSame(12, $this->resolver()->widgetStyleDefaults($theme)['border_radius']);
        $this->assertTrue($this->resolver()->siteChrome($theme)['header']['transparent']);
    }
}
