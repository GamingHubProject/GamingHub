<?php

namespace Tests\Feature;

use App\Capabilities\CapabilityGateway;
use App\Connectors\HttpRequestContract;
use App\Models\ConnectorInstance;
use GamingHub\Core\Capabilities\CapabilityValue;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Connectors\Support\FakeHttpRequester;

/**
 * Manual is now just another Provider.type in the same priority stack as
 * connector-backed rows — see CapabilityGateway::probeManualProvider() and
 * ProvidersRelationManager's unified form. This replaces Manual's old,
 * separate CapabilityBinding-only path for anything reachable from a
 * Server's provider stack (CapabilityGatewayTest's CapabilityBinding-based
 * tests still cover the bottom-of-stack "default" fallback, which is a
 * different mechanism and still exists unchanged).
 */
class ManualProviderInStackTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manual_provider_row_resolves_the_capability_it_declares(): void
    {
        $server = Server::factory()->create();

        Provider::factory()->create([
            'server_id' => $server->id,
            'type' => 'manual',
            'connector_instance_id' => null,
            'config' => ['capability' => 'server-status', 'value' => ['online' => true, 'players' => 3]],
        ]);

        $value = app(CapabilityGateway::class)->probe('server-status', $server);

        $this->assertTrue($value->isOk());
        $this->assertTrue($value->data['online']);
        $this->assertSame(3, $value->data['players']);
    }

    public function test_a_manual_provider_is_skipped_for_a_capability_it_does_not_declare(): void
    {
        $server = Server::factory()->create();

        Provider::factory()->create([
            'server_id' => $server->id,
            'type' => 'manual',
            'config' => ['capability' => 'server-status', 'value' => ['online' => true]],
        ]);

        $value = app(CapabilityGateway::class)->probe('player-positions', $server);

        $this->assertSame(CapabilityValue::UNSUPPORTED, $value->status);
    }

    public function test_a_higher_priority_connector_provider_wins_shared_fields_over_a_manual_fallback(): void
    {
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Game REST API', 'type' => 'rest', 'base_url' => 'http://game-server:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);

        Provider::factory()->create([
            'server_id' => $server->id, 'type' => 'connector', 'connector_instance_id' => $connector->id, 'priority' => 0,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);
        Provider::factory()->create([
            'server_id' => $server->id, 'type' => 'manual', 'priority' => 1,
            'config' => ['capability' => 'server-status', 'value' => ['online' => false, 'players' => 999]],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 5, 'maxplayernum' => 20]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->probe('server-status', $server);

        $this->assertTrue($value->isOk());
        $this->assertSame(5, $value->data['players']);
    }

    public function test_manual_fills_a_gap_the_higher_priority_connector_left_open(): void
    {
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Game REST API', 'type' => 'rest', 'base_url' => 'http://game-server:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);

        Provider::factory()->create([
            'server_id' => $server->id, 'type' => 'connector', 'connector_instance_id' => $connector->id, 'priority' => 0,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                // Deliberately only maps currentplayernum, not maxplayernum
                // — 'note' is a genuine gap the connector never fills,
                // unlike a fixed-shape normalizer that always sets every
                // field it recognizes (even to null).
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);
        Provider::factory()->create([
            'server_id' => $server->id, 'type' => 'manual', 'priority' => 1,
            'config' => ['capability' => 'server-status', 'value' => ['note' => 'backup EU server']],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 5, 'maxplayernum' => 20]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->probe('server-status', $server);

        $this->assertTrue($value->isOk());
        $this->assertSame(5, $value->data['players']);
        $this->assertSame('backup EU server', $value->data['note']);
    }
}
