<?php

namespace Tests\Feature;

use App\Models\Asset;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_can_be_created(): void
    {
        $game = Game::factory()->create();

        $this->assertDatabaseHas('games', ['id' => $game->id]);
    }

    public function test_provider_belongs_to_server(): void
    {
        $server = Server::factory()->create();
        $provider = Provider::factory()->create(['server_id' => $server->id]);

        $this->assertTrue($provider->server->is($server));
    }

    public function test_provider_stores_a_soft_reference_to_a_connector_instance(): void
    {
        // connector_instance_id is a plain column, not an Eloquent relation —
        // Core must never know about Platform's ConnectorInstance model.
        $provider = Provider::factory()->create([
            'connector_instance_id' => 42,
            'config' => ['server_identifier' => 'd3aac351'],
        ]);

        $this->assertSame(42, $provider->fresh()->connector_instance_id);
        $this->assertSame('d3aac351', $provider->fresh()->config['server_identifier']);
    }

    public function test_asset_id_is_unique(): void
    {
        $asset = Asset::factory()->create();

        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }
}
