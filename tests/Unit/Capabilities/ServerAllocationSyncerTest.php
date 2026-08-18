<?php

namespace Tests\Unit\Capabilities;

use App\Capabilities\ServerAllocationSyncer;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerAllocationSyncerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_allocations_for_a_server_with_none_yet(): void
    {
        $server = Server::factory()->create();

        (new ServerAllocationSyncer)->sync($server, [
            ['external_id' => 1, 'ip' => '1.2.3.4', 'ip_alias' => null, 'port' => 25565, 'is_default' => true, 'notes' => null],
            ['external_id' => 2, 'ip' => '1.2.3.4', 'ip_alias' => null, 'port' => 25566, 'is_default' => false, 'notes' => 'extra'],
        ]);

        $this->assertCount(2, $server->allocations()->get());
        $this->assertTrue($server->allocations()->where('is_default', true)->exists());
    }

    public function test_it_replaces_the_previous_set_wholesale(): void
    {
        $server = Server::factory()->create();
        $syncer = new ServerAllocationSyncer;

        $syncer->sync($server, [
            ['external_id' => 1, 'ip' => '1.2.3.4', 'ip_alias' => null, 'port' => 25565, 'is_default' => true, 'notes' => null],
        ]);
        $syncer->sync($server, [
            ['external_id' => 2, 'ip' => '5.6.7.8', 'ip_alias' => null, 'port' => 30000, 'is_default' => true, 'notes' => null],
        ]);

        $allocations = $server->allocations()->get();
        $this->assertCount(1, $allocations);
        $this->assertSame('5.6.7.8', $allocations->first()->ip);
    }

    public function test_syncing_an_empty_list_clears_existing_allocations(): void
    {
        $server = Server::factory()->create();
        $syncer = new ServerAllocationSyncer;

        $syncer->sync($server, [
            ['external_id' => 1, 'ip' => '1.2.3.4', 'ip_alias' => null, 'port' => 25565, 'is_default' => true, 'notes' => null],
        ]);
        $syncer->sync($server, []);

        $this->assertCount(0, $server->allocations()->get());
    }
}
