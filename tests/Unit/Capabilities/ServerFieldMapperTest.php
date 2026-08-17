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
