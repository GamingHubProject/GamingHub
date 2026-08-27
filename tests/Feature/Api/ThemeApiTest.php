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
}
