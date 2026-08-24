<?php

namespace Tests\Feature\Api;

use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use GamingHub\Core\Models\ServerAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_raw_status_and_stat_fields(): void
    {
        $server = Server::factory()->create(['status' => 'installing']);

        $response = $this->getJson("/api/v1/servers/{$server->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $server->id);
        $response->assertJsonPath('data.status', 'installing');
    }

    public function test_show_includes_allocations(): void
    {
        $server = Server::factory()->create();
        ServerAllocation::create([
            'server_id' => $server->id,
            'external_id' => 1,
            'ip' => '10.0.0.5',
            'port' => 25565,
            'is_default' => true,
        ]);

        $response = $this->getJson("/api/v1/servers/{$server->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.allocations');
        $response->assertJsonPath('data.allocations.0.port', 25565);
    }

    public function test_show_includes_the_owning_games_slug(): void
    {
        $game = Game::factory()->create(['slug' => 'palworld']);
        $server = Server::factory()->create(['game_id' => $game->id]);

        $response = $this->getJson("/api/v1/servers/{$server->id}");

        $response->assertOk();
        $response->assertJsonPath('data.game_slug', 'palworld');
    }

    public function test_show_404s_for_an_unknown_server(): void
    {
        $this->getJson('/api/v1/servers/999999')->assertNotFound();
    }
}
