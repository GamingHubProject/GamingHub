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

class ConnectorBackedCapabilityTest extends TestCase
{
    use RefreshDatabase;
    use InstallsFixtureConnectorPackage;

    public function test_server_status_flows_through_a_real_rest_call_via_an_admin_defined_field_mapping(): void
    {
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Test Game REST API',
            'type' => 'rest',
            'base_url' => 'http://game-server:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);

        Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players', 'maxplayernum' => 'max_players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode([
            'server_fps' => 30.0,
            'currentplayernum' => 7,
            'maxplayernum' => 32,
            'uptime' => 1000,
        ]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->get('server-status', $server);

        $this->assertTrue($value->isOk());
        $this->assertSame(7, $value->data['players']);
        $this->assertSame(32, $value->data['max_players']);
        $this->assertSame('http://game-server:8212/v1/api/metrics', $fake->lastUrl());
    }

    public function test_server_status_flows_through_a_fixed_shape_package_provided_normalizer(): void
    {
        // Stands in for a package-provided connector with its own
        // fixed-shape normalizer (the role Pelican used to play here before
        // it moved out to GamingHubProject/BasicConnectors) — proves the
        // same end-to-end flow the REST/field-mapping test above proves,
        // but for a normalizer that isn't admin-configured field-mapping.
        $this->installFixtureConnectorPackage();
        InstalledPackage::factory()->create(['slug' => 'fixture-panel', 'status' => 'enabled']);
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Test Fixture Panel',
            'type' => 'fixture-panel',
            'base_url' => 'https://panel.test',
            'credentials' => ['response' => ['state' => 'running', 'memory_bytes' => 123456, 'cpu_percent' => 5.5]],
        ]);

        Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => ['normalizer' => 'fixture-panel-status', 'call' => []],
        ]);

        $value = app(CapabilityGateway::class)->get('server-status', $server);

        $this->assertTrue($value->isOk());
        $this->assertTrue($value->data['online']);
        $this->assertSame(123456, $value->data['memory_bytes']);
    }

    public function test_connector_failure_reports_unavailable_not_a_crash(): void
    {
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Broken connector',
            'type' => 'rest',
            'base_url' => 'http://unreachable',
            'credentials' => [],
        ]);

        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(500, 'Internal Server Error');
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->get('server-status', $server);

        $this->assertSame(\GamingHub\Core\Capabilities\CapabilityValue::UNAVAILABLE, $value->status);
        $this->assertStringContainsString('HTTP 500', $provider->fresh()->error_message);
    }

    public function test_a_provider_that_recovers_has_its_error_message_cleared(): void
    {
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Flaky connector',
            'type' => 'rest',
            'base_url' => 'http://game-server:8212',
            'credentials' => [],
        ]);

        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(500, 'Internal Server Error');
        $this->app->instance(HttpRequestContract::class, $fake);
        app(CapabilityGateway::class)->probe('server-status', $server);
        $this->assertNotNull($provider->fresh()->error_message);

        $fake->willReturn(200, json_encode(['currentplayernum' => 3]));
        app(CapabilityGateway::class)->probe('server-status', $server);

        $this->assertNull($provider->fresh()->error_message);
        $this->assertSame('connected', $provider->fresh()->status);
    }

    public function test_test_provider_probes_one_provider_in_isolation_and_persists_the_result(): void
    {
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Test connector',
            'type' => 'rest',
            'base_url' => 'http://game-server:8212',
            'credentials' => [],
        ]);

        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'status' => 'disconnected',
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 4]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->testProvider($provider);

        $this->assertTrue($value->isOk());
        $this->assertSame(4, $value->data['players']);
        $this->assertSame('connected', $provider->fresh()->status);
    }

    public function test_test_provider_reports_the_failure_reason_for_a_broken_provider(): void
    {
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Broken connector',
            'type' => 'rest',
            'base_url' => 'http://unreachable',
            'credentials' => [],
        ]);

        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(500, 'Internal Server Error');
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->testProvider($provider);

        $this->assertFalse($value->isOk());
        $this->assertStringContainsString('HTTP 500', $value->error);
        $this->assertStringContainsString('HTTP 500', $provider->fresh()->error_message);
        $this->assertSame('error', $provider->fresh()->status);
    }

    public function test_a_real_probe_persists_the_raw_response_on_the_provider_row(): void
    {
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Test Game REST API',
            'type' => 'rest',
            'base_url' => 'http://game-server:8212',
            'credentials' => [],
        ]);

        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);

        $this->assertNull($provider->last_raw_response);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 4, 'server_fps' => 30.5]));
        $this->app->instance(HttpRequestContract::class, $fake);

        app(CapabilityGateway::class)->probe('server-status', $server);

        $this->assertSame(
            ['currentplayernum' => 4, 'server_fps' => 30.5],
            $provider->fresh()->last_raw_response
        );
    }

    public function test_debug_test_provider_also_persists_the_raw_response(): void
    {
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Test Game REST API',
            'type' => 'rest',
            'base_url' => 'http://game-server:8212',
            'credentials' => [],
        ]);

        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 2]));
        $this->app->instance(HttpRequestContract::class, $fake);

        app(CapabilityGateway::class)->debugTestProvider($provider);

        $this->assertSame(['currentplayernum' => 2], $provider->fresh()->last_raw_response);
    }

    public function test_a_manual_provider_never_gets_a_raw_response_persisted(): void
    {
        $server = Server::factory()->create();

        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => null,
            'type' => 'manual',
            'config' => ['capability' => 'server-status', 'value' => ['online' => true]],
        ]);

        app(CapabilityGateway::class)->probe('server-status', $server);

        $this->assertNull($provider->fresh()->last_raw_response);
    }

    public function test_disabling_the_owning_package_makes_the_capability_unavailable(): void
    {
        $this->installFixtureConnectorPackage();
        $package = InstalledPackage::factory()->create(['slug' => 'fixture-panel', 'status' => 'enabled']);
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Test Fixture Panel',
            'type' => 'fixture-panel',
            'base_url' => 'https://panel.test',
            'credentials' => ['response' => ['state' => 'running', 'memory_bytes' => 1, 'cpu_percent' => 1]],
        ]);

        Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => ['normalizer' => 'fixture-panel-status', 'call' => []],
        ]);

        // Enabled: real data flows.
        $gateway = app(CapabilityGateway::class);
        $this->assertTrue($gateway->probe('server-status', $server)->isOk());

        // Disable the package — the exact same provider, same connector, same
        // live server, must now report UNAVAILABLE. This is what makes
        // "disable" real instead of a DB flag nothing reads.
        $package->update(['status' => 'disabled']);
        $this->assertSame(
            \GamingHub\Core\Capabilities\CapabilityValue::UNAVAILABLE,
            $gateway->probe('server-status', $server)->status
        );

        // Re-enable: data returns.
        $package->update(['status' => 'enabled']);
        $this->assertTrue($gateway->probe('server-status', $server)->isOk());
    }
}
