<?php

namespace Tests\Feature;

use App\Models\Asset;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Instance;
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

    public function test_instance_belongs_to_server(): void
    {
        $server = Server::factory()->create();
        $instance = Instance::factory()->create(['server_id' => $server->id]);

        $this->assertTrue($instance->server->is($server));
    }

    public function test_provider_belongs_to_server(): void
    {
        $server = Server::factory()->create();
        $provider = Provider::factory()->create(['server_id' => $server->id]);

        $this->assertTrue($provider->server->is($server));
    }

    public function test_provider_credentials_are_encrypted_in_database(): void
    {
        $provider = Provider::factory()->create([
            'credentials' => ['token' => 'super-secret'],
        ]);

        $raw = \DB::table('providers')->where('id', $provider->id)->value('credentials');

        $this->assertStringNotContainsString('super-secret', $raw);
        $this->assertSame('super-secret', $provider->fresh()->credentials['token']);
    }

    public function test_asset_id_is_unique(): void
    {
        $asset = Asset::factory()->create();

        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }
}
