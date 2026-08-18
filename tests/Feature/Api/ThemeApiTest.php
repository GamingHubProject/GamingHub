<?php

namespace Tests\Feature\Api;

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
        $response->assertJson(['primary' => '#111111']);
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
        $response->assertJson(['primary' => '#ff0000', 'secondary' => '#222222']);
    }

    public function test_404s_for_an_unknown_game_id(): void
    {
        $this->getJson('/api/v1/theme?game_id=999999')->assertNotFound();
    }
}
