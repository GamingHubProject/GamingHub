<?php

namespace Tests\Feature\Api;

use App\Models\ServerGroup;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerGroupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_the_group_with_its_game_slug(): void
    {
        $game = Game::factory()->create(['slug' => 'ark']);
        $group = ServerGroup::create(['game_id' => $game->id, 'name' => 'Official Servers']);

        $response = $this->getJson("/api/v1/server-groups/{$group->id}");

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Official Servers');
        $response->assertJsonPath('data.game_slug', 'ark');
    }

    public function test_show_includes_server_and_running_counts(): void
    {
        $game = Game::factory()->create();
        $group = ServerGroup::create(['game_id' => $game->id, 'name' => 'Official Servers']);
        Server::factory()->create(['game_id' => $game->id, 'server_group_id' => $group->id, 'status' => 'running']);
        Server::factory()->create(['game_id' => $game->id, 'server_group_id' => $group->id, 'status' => 'running']);
        Server::factory()->create(['game_id' => $game->id, 'server_group_id' => $group->id, 'status' => 'offline']);

        $response = $this->getJson("/api/v1/server-groups/{$group->id}");

        $response->assertOk();
        $response->assertJsonPath('data.servers_count', 3);
        $response->assertJsonPath('data.running_count', 2);
    }

    public function test_show_404s_for_an_unknown_group(): void
    {
        $this->getJson('/api/v1/server-groups/999999')->assertNotFound();
    }

    public function test_for_game_lists_only_that_games_groups(): void
    {
        $ark = Game::factory()->create(['slug' => 'ark', 'status' => 'enabled']);
        $palworld = Game::factory()->create(['slug' => 'palworld', 'status' => 'enabled']);
        ServerGroup::create(['game_id' => $ark->id, 'name' => 'Ark Group']);
        ServerGroup::create(['game_id' => $palworld->id, 'name' => 'Palworld Group']);

        $response = $this->getJson('/api/v1/games/ark/server-groups');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Ark Group');
    }

    public function test_for_game_404s_for_a_disabled_game(): void
    {
        Game::factory()->create(['slug' => 'disabled-game', 'status' => 'disabled']);

        $this->getJson('/api/v1/games/disabled-game/server-groups')->assertNotFound();
    }
}
