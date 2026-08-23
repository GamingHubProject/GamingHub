<?php

namespace Tests\Feature;

use App\Connectors\HttpRequestContract;
use App\Models\ConnectorInstance;
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
        $server = Server::factory()->create(['current_players' => null, 'max_players' => null, 'status' => 'offline']);

        $connector = ConnectorInstance::create([
            'name' => 'Game REST API', 'type' => 'rest', 'base_url' => 'http://game-server:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
            'poll_interval_seconds' => 30,
        ]);
        $provider = Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $connector->id, 'status' => 'disconnected',
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players', 'maxplayernum' => 'max_players'],
            ],
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

    public function test_a_manual_provider_flows_into_server_fields_on_a_tick(): void
    {
        $server = Server::factory()->create(['current_players' => null, 'max_players' => null, 'status' => 'offline']);

        Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => null,
            'type' => 'manual',
            'status' => 'disconnected',
            'config' => [
                'capability' => 'server-status',
                'value' => [
                    'online' => 'true',
                    'players' => '5',
                    'max_players' => '32',
                ],
            ],
        ]);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        $server->refresh();
        $this->assertSame('online', $server->status);
        $this->assertSame(5, $server->current_players);
        $this->assertSame(32, $server->max_players);
    }

    public function test_an_unrelated_player_list_provider_becoming_due_does_not_force_server_status_offline(): void
    {
        // Regression: server-status must only be re-probed when a
        // server-status-capable provider is actually due — not "any
        // provider on this server is due", which let an unrelated
        // capability's independent cadence trigger a probe(respectCadence:
        // true) tick that server-status's own provider wasn't ready to
        // answer, and that got treated as a real failure ('offline').
        $server = Server::factory()->create(['status' => 'online', 'cpu_percent' => 42]);
        $originalCpuPercent = $server->fresh()->cpu_percent;

        Provider::factory()->create([
            'server_id' => $server->id,
            'type' => 'manual',
            'priority' => 0,
            'last_check' => now(), // NOT due — cadence hasn't elapsed
            'polling_cadence_seconds' => 300,
            'config' => ['capability' => 'server-status', 'value' => ['online' => 'true']],
        ]);
        Provider::factory()->create([
            'server_id' => $server->id,
            'type' => 'manual',
            'priority' => 1,
            'last_check' => null, // due
            'config' => ['capability' => 'player-list', 'value' => ['players' => '5']],
        ]);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        $server->refresh();
        $this->assertSame('online', $server->status);
        $this->assertSame($originalCpuPercent, $server->cpu_percent);
    }

    public function test_a_manual_player_list_provider_populates_player_counts_on_a_tick(): void
    {
        $server = Server::factory()->create(['current_players' => null, 'max_players' => null]);

        Provider::factory()->create([
            'server_id' => $server->id,
            'type' => 'manual',
            'last_check' => null,
            'config' => [
                'capability' => 'player-list',
                'value' => ['players' => '7', 'max_players' => '20'],
            ],
        ]);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        $server->refresh();
        $this->assertSame(7, $server->current_players);
        $this->assertSame(20, $server->max_players);
    }

    public function test_a_server_status_provider_becoming_due_does_not_force_a_player_list_write(): void
    {
        // The reverse of the first regression test — same per-capability
        // due-check has to cut both ways.
        $server = Server::factory()->create(['current_players' => 99, 'max_players' => 100]);

        Provider::factory()->create([
            'server_id' => $server->id,
            'type' => 'manual',
            'priority' => 0,
            'last_check' => null, // due
            'config' => ['capability' => 'server-status', 'value' => ['online' => 'true']],
        ]);
        Provider::factory()->create([
            'server_id' => $server->id,
            'type' => 'manual',
            'priority' => 1,
            'last_check' => now(), // NOT due
            'polling_cadence_seconds' => 300,
            'config' => ['capability' => 'player-list', 'value' => ['players' => '5', 'max_players' => '10']],
        ]);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        $server->refresh();
        $this->assertSame('online', $server->status);
        $this->assertSame(99, $server->current_players);
        $this->assertSame(100, $server->max_players);
    }

    public function test_a_connector_not_yet_due_is_skipped(): void
    {
        $server = Server::factory()->create(['current_players' => 1]);

        $connector = ConnectorInstance::create([
            'name' => 'Game REST API', 'type' => 'rest', 'base_url' => 'http://game-server:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
            'poll_interval_seconds' => 300,
            'last_polled_at' => now(),
        ]);
        Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players', 'maxplayernum' => 'max_players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 9, 'maxplayernum' => 40]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        // Untouched — the connector's own interval hasn't elapsed yet.
        $this->assertSame(1, $server->fresh()->current_players);
    }
}
