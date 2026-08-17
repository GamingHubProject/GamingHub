<?php

namespace Tests\Feature;

use App\Capabilities\CapabilityGateway;
use App\Connectors\HttpRequestContract;
use App\Models\ConnectorInstance;
use App\Models\InstalledPackage;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InstallsFixtureConnectorPackage;
use Tests\TestCase;
use Tests\Unit\Connectors\Support\FakeHttpRequester;

/**
 * A server can have more than one provider answering "server-status" at
 * once — e.g. a package-provided panel connector (cpu/memory,
 * process-level; fixture-panel stands in for Pelican, which moved out to
 * GamingHubProject/BasicConnectors) and a generic REST provider (players,
 * game-level, mapped via FieldMappingNormalizer). Neither should overwrite
 * the other's fields; priority only breaks ties when both providers set
 * the *same* key. This is the Provider priority stack itself — no
 * CapabilityBinding involved, Provider.config carries everything
 * CapabilityGateway needs.
 */
class MultiProviderMergeTest extends TestCase
{
    use RefreshDatabase;
    use InstallsFixtureConnectorPackage;

    public function test_two_providers_contribute_different_fields_without_overwriting_each_other(): void
    {
        $this->installFixtureConnectorPackage();
        InstalledPackage::factory()->create(['slug' => 'fixture-panel', 'status' => 'enabled']);
        $server = Server::factory()->create();

        $rest = ConnectorInstance::create([
            'name' => 'Game REST API', 'type' => 'rest', 'base_url' => 'http://game-server:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);
        $panel = ConnectorInstance::create([
            'name' => 'Fixture Panel', 'type' => 'fixture-panel', 'base_url' => 'https://panel.test',
            'credentials' => ['response' => ['state' => 'running', 'memory_bytes' => 999, 'cpu_percent' => 12.5]],
        ]);

        $panelProvider = Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $panel->id, 'priority' => 0,
            'config' => ['normalizer' => 'fixture-panel-status', 'call' => []],
        ]);
        $restProvider = Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $rest->id, 'priority' => 1,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players', 'maxplayernum' => 'max_players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode([
            'currentplayernum' => 5, 'maxplayernum' => 20,
        ]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->probe('server-status', $server);

        $this->assertTrue($value->isOk());
        $this->assertSame(999, $value->data['memory_bytes']);
        $this->assertSame(12.5, $value->data['cpu_percent']);
        $this->assertSame(5, $value->data['players']);
        $this->assertSame(20, $value->data['max_players']);
        // The panel provider has priority 0 (higher) and also reports
        // 'online' — its value wins the shared key over the REST provider's.
        $this->assertTrue($value->data['online']);

        $this->assertSame('connected', $panelProvider->fresh()->status);
        $this->assertSame('connected', $restProvider->fresh()->status);
    }

    public function test_one_provider_failing_does_not_blank_out_the_other_providers_fields(): void
    {
        $this->installFixtureConnectorPackage();
        InstalledPackage::factory()->create(['slug' => 'fixture-panel', 'status' => 'enabled']);
        $server = Server::factory()->create();

        $rest = ConnectorInstance::create([
            'name' => 'Game REST API', 'type' => 'rest', 'base_url' => 'http://game-server:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);
        $panel = ConnectorInstance::create([
            'name' => 'Fixture Panel', 'type' => 'fixture-panel', 'base_url' => 'https://panel.test',
            'credentials' => ['fail' => true],
        ]);

        $panelProvider = Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $panel->id, 'priority' => 0,
            'config' => ['normalizer' => 'fixture-panel-status', 'call' => []],
        ]);
        $restProvider = Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $rest->id, 'priority' => 1,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players', 'maxplayernum' => 'max_players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 5, 'maxplayernum' => 20]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->probe('server-status', $server);

        $this->assertTrue($value->isOk());
        $this->assertSame(5, $value->data['players']);
        $this->assertArrayNotHasKey('memory_bytes', $value->data);

        $this->assertSame('error', $panelProvider->fresh()->status);
        $this->assertSame('connected', $restProvider->fresh()->status);
    }

    public function test_a_provider_whose_normalizer_serves_a_different_capability_is_skipped(): void
    {
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Game REST API', 'type' => 'rest', 'base_url' => 'http://game-server:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);
        Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 5, 'maxplayernum' => 20]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->probe('player-positions', $server);

        $this->assertSame(\GamingHub\Core\Capabilities\CapabilityValue::UNSUPPORTED, $value->status);
    }
}
