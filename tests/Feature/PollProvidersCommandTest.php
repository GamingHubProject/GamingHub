<?php

namespace Tests\Feature;

use App\Connectors\HttpRequestContract;
use App\Models\ConnectorInstance;
use App\Models\InstalledPackage;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Connectors\Support\FakeHttpRequester;

class PollProvidersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_single_tick_refreshes_due_servers_and_marks_connectors_polled(): void
    {
        InstalledPackage::factory()->create(['slug' => 'palworld-integration', 'status' => 'enabled']);
        $server = Server::factory()->create(['current_players' => null, 'max_players' => null, 'status' => 'offline']);

        $connector = ConnectorInstance::create([
            'name' => 'Palworld REST', 'type' => 'rest', 'base_url' => 'http://palworld:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
            'poll_interval_seconds' => 30,
        ]);
        $provider = Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $connector->id, 'status' => 'disconnected',
            'config' => ['normalizer' => 'palworld-server-status', 'call' => ['endpoint' => '/v1/api/metrics']],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 9, 'maxplayernum' => 40, 'uptime' => 100]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        $server->refresh();
        $this->assertSame(9, $server->current_players);
        $this->assertSame(40, $server->max_players);
        $this->assertSame('online', $server->status);
        $this->assertNotNull($server->last_polled_at);
        $this->assertSame('connected', $provider->fresh()->status);
        $this->assertNotNull($connector->fresh()->last_polled_at);
    }

    public function test_a_connector_not_yet_due_is_skipped(): void
    {
        InstalledPackage::factory()->create(['slug' => 'palworld-integration', 'status' => 'enabled']);
        $server = Server::factory()->create(['current_players' => 1]);

        $connector = ConnectorInstance::create([
            'name' => 'Palworld REST', 'type' => 'rest', 'base_url' => 'http://palworld:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
            'poll_interval_seconds' => 300,
            'last_polled_at' => now(),
        ]);
        Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $connector->id,
            'config' => ['normalizer' => 'palworld-server-status', 'call' => ['endpoint' => '/v1/api/metrics']],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 9, 'maxplayernum' => 40]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        // Untouched — the connector's own interval hasn't elapsed yet.
        $this->assertSame(1, $server->fresh()->current_players);
    }
}
