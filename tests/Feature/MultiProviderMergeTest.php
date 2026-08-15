<?php

namespace Tests\Feature;

use App\Capabilities\CapabilityGateway;
use App\Connectors\HttpRequestContract;
use App\Models\ConnectorInstance;
use App\Models\InstalledPackage;
use GamingHub\Core\Models\CapabilityBinding;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Connectors\Support\FakeHttpRequester;

/**
 * A server can have more than one provider answering "server-status" at
 * once — e.g. Pelican (cpu/memory, process-level) and a Palworld REST
 * provider (players, game-level). Neither should overwrite the other's
 * fields; priority only breaks ties when both providers set the *same* key.
 */
class MultiProviderMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_providers_contribute_different_fields_without_overwriting_each_other(): void
    {
        InstalledPackage::factory()->create(['slug' => 'palworld-integration', 'status' => 'enabled']);
        InstalledPackage::factory()->create(['slug' => 'pelican-connector', 'status' => 'enabled']);
        $server = Server::factory()->create();

        $palworld = ConnectorInstance::create([
            'name' => 'Palworld REST', 'type' => 'rest', 'base_url' => 'http://palworld:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);
        $pelican = ConnectorInstance::create([
            'name' => 'Pelican', 'type' => 'pelican', 'base_url' => 'https://panel.test',
            'credentials' => ['application_token' => 'a', 'client_token' => 'c'],
        ]);

        $pelicanProvider = Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $pelican->id, 'priority' => 0,
        ]);
        $palworldProvider = Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $palworld->id, 'priority' => 1,
        ]);

        CapabilityBinding::create([
            'capability' => 'server-status', 'subject_type' => 'server', 'subject_id' => $server->id,
            'provider' => 'connector', 'priority' => 0, 'source_provider_id' => $pelicanProvider->id, 'enabled' => true,
            'value' => ['connector_instance_id' => $pelican->id, 'call' => ['server_identifier' => 'srv1'], 'normalizer' => 'pelican-server-status'],
        ]);
        CapabilityBinding::create([
            'capability' => 'server-status', 'subject_type' => 'server', 'subject_id' => $server->id,
            'provider' => 'connector', 'priority' => 1, 'source_provider_id' => $palworldProvider->id, 'enabled' => true,
            'value' => ['connector_instance_id' => $palworld->id, 'call' => ['endpoint' => '/v1/api/metrics'], 'normalizer' => 'palworld-server-status'],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturnForUrl('panel.test', 200, json_encode([
            'attributes' => ['current_state' => 'running', 'resources' => ['memory_bytes' => 999, 'cpu_absolute' => 12.5]],
        ]));
        $fake->willReturnForUrl('palworld:8212', 200, json_encode([
            'currentplayernum' => 5, 'maxplayernum' => 20, 'uptime' => 500,
        ]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->probe('server-status', $server);

        $this->assertTrue($value->isOk());
        $this->assertSame(999, $value->data['memory_bytes']);
        $this->assertSame(12.5, $value->data['cpu_percent']);
        $this->assertSame(5, $value->data['players']);
        $this->assertSame(20, $value->data['max_players']);
        // Pelican has priority 0 (higher) and also reports 'online' — its
        // value wins the shared key over Palworld's.
        $this->assertTrue($value->data['online']);

        $this->assertSame('connected', $pelicanProvider->fresh()->status);
        $this->assertSame('connected', $palworldProvider->fresh()->status);
    }

    public function test_one_provider_failing_does_not_blank_out_the_other_providers_fields(): void
    {
        InstalledPackage::factory()->create(['slug' => 'palworld-integration', 'status' => 'enabled']);
        InstalledPackage::factory()->create(['slug' => 'pelican-connector', 'status' => 'enabled']);
        $server = Server::factory()->create();

        $palworld = ConnectorInstance::create([
            'name' => 'Palworld REST', 'type' => 'rest', 'base_url' => 'http://palworld:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);
        $pelican = ConnectorInstance::create([
            'name' => 'Pelican', 'type' => 'pelican', 'base_url' => 'https://panel.test',
            'credentials' => ['application_token' => 'a', 'client_token' => 'c'],
        ]);

        $pelicanProvider = Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $pelican->id, 'priority' => 0,
        ]);
        $palworldProvider = Provider::factory()->create([
            'server_id' => $server->id, 'connector_instance_id' => $palworld->id, 'priority' => 1,
        ]);

        CapabilityBinding::create([
            'capability' => 'server-status', 'subject_type' => 'server', 'subject_id' => $server->id,
            'provider' => 'connector', 'priority' => 0, 'source_provider_id' => $pelicanProvider->id, 'enabled' => true,
            'value' => ['connector_instance_id' => $pelican->id, 'call' => ['server_identifier' => 'srv1'], 'normalizer' => 'pelican-server-status'],
        ]);
        CapabilityBinding::create([
            'capability' => 'server-status', 'subject_type' => 'server', 'subject_id' => $server->id,
            'provider' => 'connector', 'priority' => 1, 'source_provider_id' => $palworldProvider->id, 'enabled' => true,
            'value' => ['connector_instance_id' => $palworld->id, 'call' => ['endpoint' => '/v1/api/metrics'], 'normalizer' => 'palworld-server-status'],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturnForUrl('panel.test', 500, 'error');
        $fake->willReturnForUrl('palworld:8212', 200, json_encode(['currentplayernum' => 5, 'maxplayernum' => 20]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $value = app(CapabilityGateway::class)->probe('server-status', $server);

        $this->assertTrue($value->isOk());
        $this->assertSame(5, $value->data['players']);
        $this->assertArrayNotHasKey('memory_bytes', $value->data);

        $this->assertSame('error', $pelicanProvider->fresh()->status);
        $this->assertSame('connected', $palworldProvider->fresh()->status);
    }
}
