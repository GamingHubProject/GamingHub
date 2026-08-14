<?php

namespace Tests\Feature;

use App\Capabilities\CapabilityGateway;
use App\Capabilities\CapabilityValue;
use App\Models\CapabilityBinding;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_unbound_capability_is_unsupported(): void
    {
        $server = Server::factory()->create();

        $value = $this->gateway()->get('server-status', $server);

        $this->assertSame(CapabilityValue::UNSUPPORTED, $value->status);
    }

    public function test_bound_manual_capability_returns_ok_with_data(): void
    {
        $server = Server::factory()->create();

        CapabilityBinding::create([
            'capability' => 'server-status',
            'subject_type' => 'server',
            'subject_id' => $server->id,
            'provider' => 'manual',
            'value' => ['online' => true, 'players' => 12, 'max_players' => 40],
            'enabled' => true,
        ]);

        $value = $this->gateway()->get('server-status', $server);

        $this->assertTrue($value->isOk());
        $this->assertSame(12, $value->data['players']);
    }

    public function test_disabled_binding_is_unavailable(): void
    {
        $server = Server::factory()->create();

        CapabilityBinding::create([
            'capability' => 'server-status',
            'subject_type' => 'server',
            'subject_id' => $server->id,
            'provider' => 'manual',
            'value' => ['online' => true],
            'enabled' => false,
        ]);

        $value = $this->gateway()->get('server-status', $server);

        $this->assertSame(CapabilityValue::UNAVAILABLE, $value->status);
    }

    public function test_inspect_never_triggers_a_fetch_for_an_uncached_bound_capability(): void
    {
        $server = Server::factory()->create();

        CapabilityBinding::create([
            'capability' => 'server-status',
            'subject_type' => 'server',
            'subject_id' => $server->id,
            'provider' => 'manual',
            'value' => ['online' => true],
            'enabled' => true,
        ]);

        // inspect() must not populate the cache from the provider.
        $inspected = $this->gateway()->inspect('server-status', $server);
        $this->assertSame(CapabilityValue::UNAVAILABLE, $inspected->status);

        // probe() explicitly fetches and this time returns OK.
        $probed = $this->gateway()->probe('server-status', $server);
        $this->assertTrue($probed->isOk());
    }

    protected function gateway(): CapabilityGateway
    {
        return app(CapabilityGateway::class);
    }
}
