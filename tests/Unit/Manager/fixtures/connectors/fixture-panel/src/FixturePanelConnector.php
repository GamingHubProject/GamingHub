<?php

namespace GamingHubFixtures\FixturePanel;

use App\Connectors\ConnectorContract;
use App\Connectors\SupportsServerDiscovery;
use App\Models\ConnectorInstance;
use RuntimeException;

/**
 * Stands in for a package-provided, panel-style connector (the role
 * Pelican used to play in this test suite before it moved out to
 * GamingHubProject/BasicConnectors) — deliberately NOT anywhere Composer's
 * own autoloader reaches, so tests using this fixture actually exercise
 * PackageLoader's spl_autoload_register path, and implements
 * SupportsServerDiscovery so discovery-gating tests have something real to
 * check against without any dependency on a real external package.
 */
class FixturePanelConnector implements ConnectorContract, SupportsServerDiscovery
{
    public static function type(): string
    {
        return 'fixture-panel';
    }

    public function fetch(ConnectorInstance $instance, array $callConfig): array
    {
        if (($instance->credentials['fail'] ?? false)) {
            throw new RuntimeException('Fixture panel is unreachable.');
        }

        return $instance->credentials['response'] ?? ['state' => 'running', 'memory_bytes' => 999, 'cpu_percent' => 12.5];
    }

    public function listServers(ConnectorInstance $instance): array
    {
        if ($instance->credentials['discovery_fail'] ?? false) {
            throw new RuntimeException('Fixture panel discovery is unreachable.');
        }

        return $instance->credentials['servers'] ?? [
            ['identifier' => 'fixture-1', 'name' => 'Fixture Server One'],
        ];
    }
}
