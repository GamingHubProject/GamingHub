<?php

namespace Tests\Unit\Connectors;

use App\Normalizers\PalworldServerStatusNormalizer;
use App\Normalizers\PelicanServerStatusNormalizer;
use GamingHub\Core\Capabilities\CapabilityValue;
use PHPUnit\Framework\TestCase;

class NormalizerTest extends TestCase
{
    public function test_palworld_normalizer_parses_real_metrics_shape(): void
    {
        $value = (new PalworldServerStatusNormalizer)->normalize([
            'server_fps' => 30.0,
            'currentplayernum' => 3,
            'serverframetime' => 33.3,
            'maxplayernum' => 32,
            'uptime' => 12345,
            'days' => 1,
        ]);

        $this->assertTrue($value->isOk());
        $this->assertTrue($value->data['online']);
        $this->assertSame(3, $value->data['players']);
        $this->assertSame(32, $value->data['max_players']);
    }

    public function test_palworld_normalizer_is_unavailable_on_unexpected_shape(): void
    {
        $value = (new PalworldServerStatusNormalizer)->normalize(['unexpected' => true]);

        $this->assertSame(CapabilityValue::UNAVAILABLE, $value->status);
    }

    public function test_pelican_normalizer_parses_real_resources_shape(): void
    {
        $value = (new PelicanServerStatusNormalizer)->normalize([
            'object' => 'stats',
            'attributes' => [
                'current_state' => 'running',
                'is_suspended' => false,
                'resources' => [
                    'memory_bytes' => 512000000,
                    'cpu_absolute' => 12.5,
                    'uptime' => 98765,
                ],
            ],
        ]);

        $this->assertTrue($value->isOk());
        $this->assertTrue($value->data['online']);
        $this->assertSame(512000000, $value->data['memory_bytes']);
    }

    public function test_pelican_normalizer_reports_offline_state(): void
    {
        $value = (new PelicanServerStatusNormalizer)->normalize([
            'attributes' => ['current_state' => 'offline', 'resources' => []],
        ]);

        $this->assertTrue($value->isOk());
        $this->assertFalse($value->data['online']);
    }

    public function test_pelican_normalizer_is_unavailable_on_unexpected_shape(): void
    {
        $value = (new PelicanServerStatusNormalizer)->normalize(['unexpected' => true]);

        $this->assertSame(CapabilityValue::UNAVAILABLE, $value->status);
    }
}
