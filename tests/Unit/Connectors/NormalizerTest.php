<?php

namespace Tests\Unit\Connectors;

use GamingHub\Core\Capabilities\CapabilityValue;
use GamingHub\Core\Normalizers\FieldMappingNormalizer;
use GamingHub\Core\Normalizers\PelicanServerStatusNormalizer;
use PHPUnit\Framework\TestCase;

class NormalizerTest extends TestCase
{
    public function test_field_mapping_normalizer_renames_fields_per_config(): void
    {
        $value = (new FieldMappingNormalizer)->normalize(
            ['currentplayernum' => 3, 'maxplayernum' => 32],
            ['capability' => 'server-status', 'field_map' => ['currentplayernum' => 'players', 'maxplayernum' => 'max_players']],
        );

        $this->assertTrue($value->isOk());
        $this->assertSame('server-status', $value->capability);
        $this->assertSame(3, $value->data['players']);
        $this->assertSame(32, $value->data['max_players']);
    }

    public function test_field_mapping_normalizer_synthesizes_online_when_something_mapped(): void
    {
        $value = (new FieldMappingNormalizer)->normalize(
            ['hp' => 100],
            ['capability' => 'server-status', 'field_map' => ['hp' => 'health']],
        );

        $this->assertTrue($value->data['online']);
    }

    public function test_field_mapping_normalizer_does_not_override_an_explicitly_mapped_online_field(): void
    {
        $value = (new FieldMappingNormalizer)->normalize(
            ['is_up' => false],
            ['capability' => 'server-status', 'field_map' => ['is_up' => 'online']],
        );

        $this->assertFalse($value->data['online']);
    }

    public function test_field_mapping_normalizer_is_unavailable_when_no_mapped_field_is_present(): void
    {
        $value = (new FieldMappingNormalizer)->normalize(
            ['unexpected' => true],
            ['capability' => 'server-status', 'field_map' => ['currentplayernum' => 'players']],
        );

        $this->assertSame(CapabilityValue::UNAVAILABLE, $value->status);
    }

    public function test_field_mapping_normalizer_declares_the_capability_from_its_config(): void
    {
        $normalizer = new FieldMappingNormalizer;

        $this->assertSame('server-status', $normalizer->capability(['capability' => 'server-status']));
        $this->assertSame('', $normalizer->capability());
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

    public function test_pelican_normalizer_declares_its_capability(): void
    {
        $this->assertSame('server-status', (new PelicanServerStatusNormalizer)->capability());
    }
}
