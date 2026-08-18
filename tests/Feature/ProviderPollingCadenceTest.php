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

/**
 * Priority 13 Part B — per-provider polling cadence. probe()'s default
 * (respectCadence: false) is exercised everywhere else (MultiProviderMergeTest,
 * ProviderDebugTestTest, etc.) and must keep forcing a live check always;
 * these tests are specifically about the respectCadence: true path
 * PollProviders uses.
 */
class ProviderPollingCadenceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeServerWithRestProvider(array $providerOverrides = []): array
    {
        $server = Server::factory()->create(['current_players' => 1]);

        $connector = ConnectorInstance::create([
            'name' => 'Game REST API', 'type' => 'rest', 'base_url' => 'http://game-server:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
            'poll_interval_seconds' => 1,
        ]);

        $provider = Provider::factory()->create(array_merge([
            'server_id' => $server->id, 'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ], $providerOverrides));

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 9]));
        $this->app->instance(HttpRequestContract::class, $fake);

        return [$server, $provider];
    }

    public function test_a_never_checked_provider_is_due_regardless_of_cadence(): void
    {
        [$server, $provider] = $this->makeServerWithRestProvider(['polling_cadence_seconds' => 300, 'last_check' => null]);

        $value = app(CapabilityGateway::class)->probe('server-status', $server, respectCadence: true);

        $this->assertTrue($value->isOk());
        $this->assertSame(9, $value->data['players']);
    }

    public function test_a_provider_within_its_cadence_window_is_skipped(): void
    {
        [$server, $provider] = $this->makeServerWithRestProvider([
            'polling_cadence_seconds' => 300,
            'last_check' => now()->subSeconds(10),
        ]);

        $value = app(CapabilityGateway::class)->probe('server-status', $server, respectCadence: true);

        // Skipped, not failed — no provider contributed data, but nothing
        // was actually attempted either.
        $this->assertFalse($value->isOk());
    }

    public function test_a_provider_past_its_cadence_window_is_probed_again(): void
    {
        [$server, $provider] = $this->makeServerWithRestProvider([
            'polling_cadence_seconds' => 30,
            'last_check' => now()->subSeconds(31),
        ]);

        $value = app(CapabilityGateway::class)->probe('server-status', $server, respectCadence: true);

        $this->assertTrue($value->isOk());
        $this->assertSame(9, $value->data['players']);
    }

    public function test_probe_without_respect_cadence_always_checks_regardless_of_last_check(): void
    {
        [$server, $provider] = $this->makeServerWithRestProvider([
            'polling_cadence_seconds' => 300,
            'last_check' => now()->subSeconds(1),
        ]);

        $value = app(CapabilityGateway::class)->probe('server-status', $server);

        $this->assertTrue($value->isOk());
    }

    /**
     * The bug caught while wiring this up: skipping every provider for
     * cadence reasons must never look identical to every provider having
     * genuinely failed. PollProviders::refreshServer() is where this is
     * actually guarded (it skips the whole tick rather than calling
     * probe() at all when nothing is due) — this test exercises that
     * through the real command, not just CapabilityGateway in isolation.
     */
    public function test_a_server_with_no_due_providers_is_left_untouched_by_a_poll_tick(): void
    {
        [$server, $provider] = $this->makeServerWithRestProvider([
            'polling_cadence_seconds' => 300,
            'last_check' => now()->subSeconds(10),
        ]);
        $server->update(['status' => 'online', 'current_players' => 7]);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        $server->refresh();
        $this->assertSame('online', $server->status);
        $this->assertSame(7, $server->current_players);
    }

    public function test_a_server_with_a_due_provider_is_refreshed_by_a_poll_tick(): void
    {
        [$server, $provider] = $this->makeServerWithRestProvider([
            'polling_cadence_seconds' => 30,
            'last_check' => null,
        ]);

        $this->artisan('gaming-hub:poll-providers', ['--once' => true])->assertExitCode(0);

        $server->refresh();
        $this->assertSame(9, $server->current_players);
        $this->assertNotNull($provider->fresh()->last_check);
    }
}
