<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\PageLayout;
use App\Models\SiteOption;
use App\Models\Theme;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_platform_tokens_with_no_query_params(): void
    {
        Theme::create([
            'name' => 'Default',
            'level' => Theme::LEVEL_PLATFORM,
            'tokens' => ['primary' => '#111111'],
            'is_default' => true,
        ]);

        $response = $this->getJson('/api/v1/theme');

        $response->assertOk();
        $response->assertJson(['tokens' => ['primary' => '#111111']]);
    }

    public function test_merges_game_tokens_over_platform_tokens(): void
    {
        Theme::create([
            'name' => 'Default',
            'level' => Theme::LEVEL_PLATFORM,
            'tokens' => ['primary' => '#111111', 'secondary' => '#222222'],
            'is_default' => true,
        ]);
        $game = Game::factory()->create();
        Theme::create([
            'name' => 'Ark Theme',
            'level' => Theme::LEVEL_GAME,
            'game_id' => $game->id,
            'tokens' => ['primary' => '#ff0000'],
            'is_default' => true,
        ]);

        $response = $this->getJson("/api/v1/theme?game_id={$game->id}");

        $response->assertOk();
        $response->assertJson(['tokens' => ['primary' => '#ff0000', 'secondary' => '#222222']]);
    }

    public function test_404s_for_an_unknown_game_id(): void
    {
        $this->getJson('/api/v1/theme?game_id=999999')->assertNotFound();
    }

    public function test_font_is_null_when_neither_page_nor_global_font_is_set(): void
    {
        $response = $this->getJson('/api/v1/theme?subject_type=home');

        $response->assertOk();
        $response->assertJsonPath('font', null);
    }

    public function test_font_falls_back_to_the_global_default(): void
    {
        $asset = Asset::factory()->create();
        SiteOption::current()->update(['values' => ['font_asset_id' => $asset->id]]);

        $response = $this->getJson('/api/v1/theme?subject_type=home');

        $response->assertOk();
        $response->assertJsonPath('font.family', "gh-font-{$asset->id}");
        $response->assertJsonPath('font.url', $asset->url);
    }

    public function test_font_uses_the_pages_own_override_instead_of_the_global_default(): void
    {
        $globalAsset = Asset::factory()->create();
        SiteOption::current()->update(['values' => ['font_asset_id' => $globalAsset->id]]);
        $pageAsset = Asset::factory()->create();
        PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID, 'font_asset_id' => $pageAsset->id]);

        $response = $this->getJson('/api/v1/theme?subject_type=home');

        $response->assertOk();
        $response->assertJsonPath('font.family', "gh-font-{$pageAsset->id}");
    }

    public function test_font_syncs_to_global_when_the_pages_override_is_null(): void
    {
        $globalAsset = Asset::factory()->create();
        SiteOption::current()->update(['values' => ['font_asset_id' => $globalAsset->id]]);
        PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID, 'font_asset_id' => null]);

        $response = $this->getJson('/api/v1/theme?subject_type=home');

        $response->assertOk();
        $response->assertJsonPath('font.family', "gh-font-{$globalAsset->id}");
    }

    // --- Widget style defaults ---

    public function test_widget_style_defaults_to_an_empty_object_when_nothing_is_configured(): void
    {
        $response = $this->getJson('/api/v1/theme');

        $response->assertOk();
        $response->assertJsonPath('widgetStyle', []);
    }

    public function test_widget_style_returns_the_configured_global_defaults(): void
    {
        SiteOption::current()->update(['values' => [
            'widget_style_defaults' => [
                'border_enabled' => true,
                'border_thickness' => 3,
                'text_size' => 18,
                'text_color' => '#ff0000',
                'background_color' => '#000000',
                'background_opacity' => 0.5,
            ],
        ]]);

        $response = $this->getJson('/api/v1/theme');

        $response->assertOk();
        $response->assertJsonPath('widgetStyle.border_enabled', true);
        $response->assertJsonPath('widgetStyle.border_thickness', 3);
        $response->assertJsonPath('widgetStyle.text_color', '#ff0000');
        $response->assertJsonPath('widgetStyle.background_opacity', 0.5);
    }

    public function test_widget_style_is_unaffected_by_game_or_page_scoping(): void
    {
        SiteOption::current()->update(['values' => ['widget_style_defaults' => ['border_enabled' => true]]]);
        $game = Game::factory()->create();

        $response = $this->getJson("/api/v1/theme?game_id={$game->id}&subject_type=game&subject_id={$game->id}");

        $response->assertOk();
        $response->assertJsonPath('widgetStyle.border_enabled', true);
    }

    public function test_widget_style_carries_the_extended_background_defaults(): void
    {
        SiteOption::current()->update(['values' => [
            'widget_style_defaults' => [
                'background_type' => 'pattern',
                'background_pattern' => 'dots',
                'background_pattern_color' => '#112233',
                'background_image_fit' => 'tile',
            ],
        ]]);

        $response = $this->getJson('/api/v1/theme');

        $response->assertJsonPath('widgetStyle.background_type', 'pattern');
        $response->assertJsonPath('widgetStyle.background_pattern', 'dots');
        $response->assertJsonPath('widgetStyle.background_pattern_color', '#112233');
        $response->assertJsonPath('widgetStyle.background_image_fit', 'tile');
    }

    // --- Site chrome (header transparency + favicon) ---

    public function test_site_chrome_defaults_to_an_opaque_header_and_no_favicon(): void
    {
        $response = $this->getJson('/api/v1/theme');

        $response->assertOk();
        $response->assertJsonPath('site.header_transparent', false);
        $response->assertJsonPath('site.favicon_url', null);
    }

    public function test_site_chrome_reflects_the_configured_header_transparency(): void
    {
        SiteOption::current()->update(['values' => ['header_transparent' => true]]);

        $this->getJson('/api/v1/theme')->assertJsonPath('site.header_transparent', true);
    }

    public function test_site_chrome_resolves_the_favicon_asset_to_a_url(): void
    {
        $asset = Asset::factory()->create(['url' => 'https://cdn.example/favicon.png']);
        SiteOption::current()->update(['values' => ['favicon_asset_id' => $asset->id]]);

        $this->getJson('/api/v1/theme')->assertJsonPath('site.favicon_url', 'https://cdn.example/favicon.png');
    }

    public function test_site_chrome_survives_a_favicon_asset_that_was_deleted(): void
    {
        // A dangling id shouldn't 500 the whole theme endpoint — every
        // page's styling depends on this response.
        SiteOption::current()->update(['values' => ['favicon_asset_id' => 999999]]);

        $this->getJson('/api/v1/theme')->assertOk()->assertJsonPath('site.favicon_url', null);
    }

    public function test_site_chrome_is_unaffected_by_game_or_page_scoping(): void
    {
        SiteOption::current()->update(['values' => ['header_transparent' => true]]);
        $game = Game::factory()->create();

        $this->getJson("/api/v1/theme?game_id={$game->id}")->assertJsonPath('site.header_transparent', true);
    }
}
