<?php

namespace Tests\Unit\Capabilities;

use App\Capabilities\ServerFieldMapper;
use PHPUnit\Framework\TestCase;

class ServerFieldMapperTest extends TestCase
{
    public function test_it_maps_online_to_status(): void
    {
        $mapped = (new ServerFieldMapper)->map(['online' => true]);

        $this->assertSame(['status' => 'online'], $mapped);
    }

    public function test_it_maps_offline_when_online_is_false(): void
    {
        $mapped = (new ServerFieldMapper)->map(['online' => false]);

        $this->assertSame(['status' => 'offline'], $mapped);
    }

    public function test_it_maps_players_to_current_players(): void
    {
        $mapped = (new ServerFieldMapper)->map(['players' => 5]);

        $this->assertSame(['current_players' => 5], $mapped);
    }

    public function test_it_maps_max_players_unchanged(): void
    {
        $mapped = (new ServerFieldMapper)->map(['max_players' => 32]);

        $this->assertSame(['max_players' => 32], $mapped);
    }

    public function test_it_maps_cpu_percent_to_cpu_usage_percent(): void
    {
        $mapped = (new ServerFieldMapper)->map(['cpu_percent' => 45.2]);

        $this->assertSame(['cpu_usage_percent' => 45.2], $mapped);
    }

    public function test_it_maps_memory_bytes_to_memory_usage_bytes(): void
    {
        $mapped = (new ServerFieldMapper)->map(['memory_bytes' => 123456]);

        $this->assertSame(['memory_usage_bytes' => 123456], $mapped);
    }

    public function test_it_maps_all_fields_together(): void
    {
        $mapped = (new ServerFieldMapper)->map([
            'online' => true,
            'players' => 5,
            'max_players' => 32,
            'cpu_percent' => 45.2,
            'memory_bytes' => 123456,
        ]);

        $this->assertSame([
            'status' => 'online',
            'current_players' => 5,
            'max_players' => 32,
            'cpu_usage_percent' => 45.2,
            'memory_usage_bytes' => 123456,
        ], $mapped);
    }

    public function test_it_prefers_server_status_over_online_when_both_present(): void
    {
        $mapped = (new ServerFieldMapper)->map(['server_status' => 'installing', 'online' => true]);

        $this->assertSame(['status' => 'installing'], $mapped);
    }

    public function test_it_maps_resource_limit_fields(): void
    {
        $mapped = (new ServerFieldMapper)->map([
            'cpu_current' => 45.2,
            'cpu_limit' => 100,
            'cpu_percent_of_limit' => 45.2,
            'memory_current' => 512 * 1024 * 1024,
            'memory_limit' => 2048 * 1024 * 1024,
            'memory_percent_of_limit' => 25.0,
            'disk_current' => 1024,
            'disk_limit' => 10240,
            'disk_percent_of_limit' => 10.0,
            'network_rx' => 100,
            'network_tx' => 200,
            'node_name' => 'node-1',
        ]);

        $this->assertSame([
            'cpu_current' => 45.2,
            'cpu_limit' => 100,
            'cpu_percent' => 45.2,
            'memory_current' => 512 * 1024 * 1024,
            'memory_limit' => 2048 * 1024 * 1024,
            'memory_percent' => 25.0,
            'disk_current' => 1024,
            'disk_limit' => 10240,
            'disk_percent' => 10.0,
            'network_rx' => 100,
            'network_tx' => 200,
            'node_name' => 'node-1',
        ], $mapped);
    }

    public function test_it_maps_supported_features(): void
    {
        $features = ['resources' => true, 'backups' => false, 'databases' => true];

        $mapped = (new ServerFieldMapper)->map(['supported_features' => $features]);

        $this->assertSame(['supported_features' => $features], $mapped);
    }

    public function test_allocations_are_not_mapped_to_a_flat_column(): void
    {
        $mapped = (new ServerFieldMapper)->map(['allocations' => [['ip' => '1.2.3.4', 'port' => 25565]]]);

        $this->assertArrayNotHasKey('allocations', $mapped);
    }

    public function test_unmapped_keys_are_ignored(): void
    {
        $mapped = (new ServerFieldMapper)->map(['uptime' => 999, 'server_fps' => 30.0]);

        $this->assertSame([], $mapped);
    }

    public function test_missing_keys_are_simply_absent_not_null(): void
    {
        $mapped = (new ServerFieldMapper)->map(['players' => 5]);

        $this->assertArrayNotHasKey('status', $mapped);
        $this->assertArrayNotHasKey('max_players', $mapped);
    }

    public function test_empty_input_maps_to_empty_output(): void
    {
        $this->assertSame([], (new ServerFieldMapper)->map([]));
    }
}
