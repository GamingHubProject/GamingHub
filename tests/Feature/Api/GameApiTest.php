<?php

namespace Tests\Feature\Api;

use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_enabled_games(): void
    {
        Game::factory()->create(['name' => 'Ark', 'status' => 'enabled']);
        Game::factory()->create(['name' => 'Disabled Game', 'status' => 'disabled']);

        $response = $this->getJson('/api/v1/games');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Ark');
    }

    public function test_show_returns_a_game_by_slug(): void
    {
        $game = Game::factory()->create(['name' => 'Ark', 'slug' => 'ark', 'status' => 'enabled']);

        $response = $this->getJson('/api/v1/games/ark');

        $response->assertOk();
        $response->assertJsonPath('data.id', $game->id);
        $response->assertJsonPath('data.slug', 'ark');
    }

    public function test_show_404s_for_a_disabled_game(): void
    {
        Game::factory()->create(['slug' => 'disabled-game', 'status' => 'disabled']);

        $this->getJson('/api/v1/games/disabled-game')->assertNotFound();
    }

    public function test_show_404s_for_an_unknown_slug(): void
    {
        $this->getJson('/api/v1/games/nonexistent')->assertNotFound();
    }

    public function test_servers_lists_that_games_servers(): void
    {
        $ark = Game::factory()->create(['slug' => 'ark', 'status' => 'enabled']);
        $palworld = Game::factory()->create(['slug' => 'palworld', 'status' => 'enabled']);
        Server::factory()->create(['game_id' => $ark->id, 'name' => 'Ragnarok']);
        Server::factory()->create(['game_id' => $palworld->id, 'name' => 'Fjordur']);

        $response = $this->getJson('/api/v1/games/ark/servers');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Ragnarok');
    }

    public function test_servers_404s_for_a_disabled_game(): void
    {
        Game::factory()->create(['slug' => 'disabled-game', 'status' => 'disabled']);

        $this->getJson('/api/v1/games/disabled-game/servers')->assertNotFound();
    }
}
