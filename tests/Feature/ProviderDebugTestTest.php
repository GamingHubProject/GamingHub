<?php

namespace Tests\Feature;

use App\Capabilities\CapabilityGateway;
use App\Connectors\HttpRequestContract;
use App\Models\ConnectorInstance;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Connectors\Support\FakeHttpRequester;

class ProviderDebugTestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_connector_test_returns_raw_normalized_and_server_preview(): void
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
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players', 'maxplayernum' => 'max_players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 5, 'maxplayernum' => 32, 'uptime' => 999]));
        $this->app->instance(HttpRequestContract::class, $fake);

        $result = app(CapabilityGateway::class)->debugTestProvider($provider);

        $this->assertTrue($result->ok);
        $this->assertNull($result->error);
        $this->assertSame('server-status', $result->capability);

        // Raw is the untouched connector payload — including fields the
        // normalizer never maps (uptime), proving it's genuinely raw and
        // not just a copy of the normalized output.
        $this->assertSame(5, $result->raw['currentplayernum']);
        $this->assertSame(999, $result->raw['uptime']);

        $this->assertSame(5, $result->normalized['players']);
        $this->assertSame(32, $result->normalized['max_players']);
        $this->assertArrayNotHasKey('uptime', $result->normalized);

        $this->assertSame(5, $result->serverPreview['current_players']);
        $this->assertSame(32, $result->serverPreview['max_players']);

        $this->assertSame('connected', $provider->fresh()->status);
    }

    public function test_a_failed_connector_test_has_no_raw_or_normalized_and_a_full_error_message(): void
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

        $result = app(CapabilityGateway::class)->debugTestProvider($provider);

        $this->assertFalse($result->ok);
        $this->assertNull($result->raw);
        $this->assertNull($result->normalized);
        $this->assertSame([], $result->serverPreview);
        $this->assertStringContainsString('HTTP 500', $result->error);
        $this->assertSame('error', $provider->fresh()->status);
    }

    public function test_a_failed_test_captures_the_warning_log_line_emitted_during_the_call(): void
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

        $result = app(CapabilityGateway::class)->debugTestProvider($provider);

        $this->assertNotEmpty($result->logs);
        $this->assertTrue(collect($result->logs)->contains(fn (string $line) => str_contains($line, 'Provider debug test failed')));
    }

    public function test_a_manual_provider_has_identical_raw_and_normalized_output(): void
    {
        $server = Server::factory()->create();
        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => null,
            'type' => 'manual',
            'config' => [
                'capability' => 'server-status',
                'value' => ['online' => 'true', 'players' => '5', 'max_players' => '32'],
            ],
        ]);

        $result = app(CapabilityGateway::class)->debugTestProvider($provider);

        $this->assertTrue($result->ok);
        $this->assertSame($result->raw, $result->normalized);
        $this->assertSame('online', $result->serverPreview['status']);
        $this->assertSame('5', $result->serverPreview['current_players']);
    }

    public function test_a_misconfigured_provider_reports_a_clear_error_with_no_raw_or_normalized(): void
    {
        $server = Server::factory()->create();
        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => null,
            'type' => 'manual',
            'config' => [],
        ]);

        $result = app(CapabilityGateway::class)->debugTestProvider($provider);

        $this->assertFalse($result->ok);
        $this->assertNull($result->raw);
        $this->assertNull($result->normalized);
        $this->assertStringContainsString('missing a valid capability', $result->error);
    }
}
