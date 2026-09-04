<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\PageLayout;
use App\Models\ThemeAssignment;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithThemes;
use Tests\TestCase;

class ThemeApiTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithThemes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeThemeDisk();
        ThemeAssignment::query()->delete();
    }

    // --- Tokens ------------------------------------------------------

    public function test_returns_the_platform_themes_tokens_with_no_query_params(): void
    {
        $this->makeTheme('Default', ['tokens' => ['accent' => '#111111']], ThemeAssignment::LEVEL_PLATFORM);

        $this->getJson('/api/v1/theme')
            ->assertOk()
            ->assertJson(['tokens' => ['accent' => '#111111']]);
    }

    public function test_merges_game_tokens_over_platform_tokens(): void
    {
        $game = Game::factory()->create();
        $this->makeTheme('Default', ['tokens' => ['accent' => '#111111', 'surface' => '#222222']], ThemeAssignment::LEVEL_PLATFORM);
        $this->makeTheme('Ark', ['tokens' => ['accent' => '#ff0000']], ThemeAssignment::LEVEL_GAME, gameId: $game->id);

        $this->getJson("/api/v1/theme?game_id={$game->id}")
            ->assertOk()
            ->assertJson(['tokens' => ['accent' => '#ff0000', 'surface' => '#222222']]);
    }

    public function test_returns_empty_tokens_when_nothing_is_assigned(): void
    {
        $this->getJson('/api/v1/theme')->assertOk()->assertJsonPath('tokens', []);
    }

    public function test_404s_for_an_unknown_game_id(): void
    {
        $this->getJson('/api/v1/theme?game_id=999999')->assertNotFound();
    }

    // --- Font --------------------------------------------------------

    public function test_font_is_null_when_neither_the_page_nor_the_theme_sets_one(): void
    {
        $this->makeTheme('Default', [], ThemeAssignment::LEVEL_PLATFORM);

        $this->getJson('/api/v1/theme')->assertOk()->assertJsonPath('font', null);
    }

    public function test_font_comes_from_the_theme_when_the_page_has_no_override(): void
    {
        $theme = $this->makeTheme('Default', [], ThemeAssignment::LEVEL_PLATFORM);
        $this->putThemeFile($theme, 'font/Inter.woff2');
        // Re-save so the bundle references the file now sitting in it.
        app(\App\Experience\ThemeStorage::class)->writeBundle($theme, tap($theme->bundle(), function ($b) {
            $b->fontFile = 'font/Inter.woff2';
            $b->fontFamily = 'Inter';
        }));

        $this->getJson('/api/v1/theme')
            ->assertOk()
            ->assertJsonPath('font.family', 'Inter');
    }

    public function test_a_pages_own_font_override_beats_the_themes(): void
    {
        $theme = $this->makeTheme('Default', [], ThemeAssignment::LEVEL_PLATFORM);
        $this->putThemeFile($theme, 'font/Inter.woff2');
        app(\App\Experience\ThemeStorage::class)->writeBundle($theme, tap($theme->bundle(), function ($b) {
            $b->fontFile = 'font/Inter.woff2';
            $b->fontFamily = 'Inter';
        }));

        // The page-level override deliberately still points at the shared
        // library, not into the theme it's overriding.
        $asset = Asset::factory()->create(['url' => 'https://cdn.example/page.woff2']);
        PageLayout::create([
            'subject_type' => 'home',
            'subject_id' => PageLayout::SINGLETON_SUBJECT_ID,
            'font_asset_id' => $asset->id,
        ]);

        $this->getJson('/api/v1/theme?subject_type=home&subject_id='.PageLayout::SINGLETON_SUBJECT_ID)
            ->assertOk()
            ->assertJsonPath('font.family', "gh-font-{$asset->id}")
            ->assertJsonPath('font.url', 'https://cdn.example/page.woff2');
    }

    // --- Widget style ------------------------------------------------

    public function test_widget_style_is_empty_when_the_theme_sets_none(): void
    {
        $this->makeTheme('Default', [], ThemeAssignment::LEVEL_PLATFORM);

        $this->getJson('/api/v1/theme')->assertOk()->assertJsonPath('widgetStyle', []);
    }

    public function test_widget_style_comes_from_the_theme(): void
    {
        $this->makeTheme('Default', [
            'widget_style' => [
                'border_enabled' => true, 'border_thickness' => 3, 'text_color' => '#ff0000',
                'background_type' => 'pattern', 'background_pattern' => 'dots',
                'background_pattern_color' => '#112233', 'background_image_fit' => 'tile',
            ],
        ], ThemeAssignment::LEVEL_PLATFORM);

        $this->getJson('/api/v1/theme')
            ->assertJsonPath('widgetStyle.border_thickness', 3)
            ->assertJsonPath('widgetStyle.text_color', '#ff0000')
            ->assertJsonPath('widgetStyle.background_type', 'pattern')
            ->assertJsonPath('widgetStyle.background_pattern', 'dots')
            ->assertJsonPath('widgetStyle.background_image_fit', 'tile');
    }

    public function test_widget_style_narrows_with_the_theme_scope(): void
    {
        // It used to be global regardless of scope; now it travels with
        // whichever theme is in effect, so a game theme can carry its own.
        $game = Game::factory()->create();
        $this->makeTheme('Default', ['widget_style' => ['border_thickness' => 1]], ThemeAssignment::LEVEL_PLATFORM);
        $this->makeTheme('Ark', ['widget_style' => ['border_thickness' => 6]], ThemeAssignment::LEVEL_GAME, gameId: $game->id);

        $this->getJson('/api/v1/theme')->assertJsonPath('widgetStyle.border_thickness', 1);
        $this->getJson("/api/v1/theme?game_id={$game->id}")->assertJsonPath('widgetStyle.border_thickness', 6);
    }

    // --- Site chrome -------------------------------------------------

    public function test_site_chrome_defaults_to_an_opaque_header_and_no_favicon(): void
    {
        $this->makeTheme('Default', [], ThemeAssignment::LEVEL_PLATFORM);

        $this->getJson('/api/v1/theme')
            ->assertJsonPath('site.header_transparent', false)
            ->assertJsonPath('site.favicon_url', null);
    }

    public function test_site_chrome_reflects_the_themes_header_transparency(): void
    {
        $this->makeTheme('Default', ['header_transparent' => true], ThemeAssignment::LEVEL_PLATFORM);

        $this->getJson('/api/v1/theme')->assertJsonPath('site.header_transparent', true);
    }

    public function test_site_chrome_resolves_the_favicon_to_a_url_from_the_themes_own_folder(): void
    {
        $theme = $this->makeTheme('Default', [], ThemeAssignment::LEVEL_PLATFORM);
        $this->putThemeFile($theme, 'favicon/icon.png');
        app(\App\Experience\ThemeStorage::class)->writeBundle($theme, tap($theme->bundle(), function ($b) {
            $b->faviconFile = 'favicon/icon.png';
        }));

        $url = $this->getJson('/api/v1/theme')->json('site.favicon_url');

        $this->assertNotNull($url);
        $this->assertStringContainsString("themes/{$theme->slug}/favicon/icon.png", $url);
    }

    public function test_a_favicon_referencing_a_missing_file_resolves_to_null_rather_than_a_broken_url(): void
    {
        $theme = $this->makeTheme('Default', ['favicon_file' => 'favicon/gone.png'], ThemeAssignment::LEVEL_PLATFORM);

        $this->getJson('/api/v1/theme')->assertOk()->assertJsonPath('site.favicon_url', null);
    }

    public function test_the_endpoint_still_answers_when_no_theme_exists_at_all(): void
    {
        // Every page's styling depends on this response, so an install
        // mid-setup must not get a 500 out of it.
        $this->getJson('/api/v1/theme')
            ->assertOk()
            ->assertJsonPath('font', null)
            ->assertJsonPath('site.header_transparent', false);
    }
}
