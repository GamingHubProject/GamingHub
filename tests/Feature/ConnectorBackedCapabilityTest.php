<?php

namespace Tests\Feature;

use App\Capabilities\CapabilityGateway;
use App\Connectors\HttpRequestContract;
use App\Models\ConnectorInstance;
use App\Models\InstalledPackage;
use GamingHub\Core\Models\CapabilityBinding;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Connectors\Support\FakeHttpRequester;

class ConnectorBackedCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_status_flows_through_a_real_palworld_style_rest_call(): void
    {
        InstalledPackage::factory()->create(['slug' => 'palworld-integration', 'status' => 'enabled']);
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Test Palworld REST',
            'type' => 'rest',
            'base_url' => 'http://palworld:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);

        CapabilityBinding::create([
            'capability' => 'server-status',
            'subject_type' => 'server',
            'subject_id' => $server->id,
            'provider' => 'connector',
            'enabled' => true,
            'value' => [
                'connector_instance_id' => $connector->id,
                'call' => ['endpoint' => '/v1/api/metrics'],
                'normalizer' => 'palworld-server-status',
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
        $this->assertSame('http://palworld:8212/v1/api/metrics', $fake->lastUrl());
    }

    public function test_server_status_flows_through_a_real_pelican_style_call(): void
    {
        InstalledPackage::factory()->create(['slug' => 'pelican-connector', 'status' => 'enabled']);
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Test Pelican',
            'type' => 'pelican',
            'base_url' => 'https://panel.test',
            'credentials' => ['token' => 'ptlc_test'],
        ]);

        CapabilityBinding::create([
            'capability' => 'server-status',
            'subject_type' => 'server',
            'subject_id' => $server->id,
            'provider' => 'connector',
            'enabled' => true,
            'value' => [
                'connector_instance_id' => $connector->id,
                'call' => ['server_identifier' => 'srv123'],
                'normalizer' => 'pelican-server-status',
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode([
            'attributes' => [
                'current_state' => 'running',
                'resources' => ['memory_bytes' => 123456, 'cpu_absolute' => 5.5],
            ],
        ]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->get('server-status', $server);

        $this->assertTrue($value->isOk());
        $this->assertTrue($value->data['online']);
        $this->assertSame(
            'https://panel.test/api/client/servers/srv123/resources',
            $fake->lastUrl()
        );
    }

    public function test_connector_failure_reports_unavailable_not_a_crash(): void
    {
        InstalledPackage::factory()->create(['slug' => 'palworld-integration', 'status' => 'enabled']);
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Broken connector',
            'type' => 'rest',
            'base_url' => 'http://unreachable',
            'credentials' => [],
        ]);

        CapabilityBinding::create([
            'capability' => 'server-status',
            'subject_type' => 'server',
            'subject_id' => $server->id,
            'provider' => 'connector',
            'enabled' => true,
            'value' => [
                'connector_instance_id' => $connector->id,
                'call' => ['endpoint' => '/v1/api/metrics'],
                'normalizer' => 'palworld-server-status',
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(500, 'Internal Server Error');
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->get('server-status', $server);

        $this->assertSame(\GamingHub\Core\Capabilities\CapabilityValue::UNAVAILABLE, $value->status);
    }

    public function test_disabling_the_owning_package_makes_the_capability_unavailable(): void
    {
        $package = InstalledPackage::factory()->create(['slug' => 'palworld-integration', 'status' => 'enabled']);
        $server = Server::factory()->create();

        $connector = ConnectorInstance::create([
            'name' => 'Test Palworld REST',
            'type' => 'rest',
            'base_url' => 'http://palworld:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);

        $binding = CapabilityBinding::create([
            'capability' => 'server-status',
            'subject_type' => 'server',
            'subject_id' => $server->id,
            'provider' => 'connector',
            'enabled' => true,
            'value' => [
                'connector_instance_id' => $connector->id,
                'call' => ['endpoint' => '/v1/api/metrics'],
                'normalizer' => 'palworld-server-status',
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 7, 'maxplayernum' => 32]));
        $this->app->instance(HttpRequestContract::class, $fake);

        // Enabled: real data flows.
        $gateway = app(CapabilityGateway::class);
        $this->assertTrue($gateway->probe('server-status', $server)->isOk());

        // Disable the package — the exact same binding, same connector, same
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
