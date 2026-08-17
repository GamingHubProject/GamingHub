<?php

namespace GamingHubFixtures\FixturePanel;

use GamingHub\Core\Capabilities\CapabilityValue;
use GamingHub\Core\Contracts\NormalizerContract;

/**
 * Pairs with FixturePanelConnector — stands in for a package-provided,
 * fixed-shape normalizer (the role PelicanServerStatusNormalizer used to
 * play before it moved out to GamingHubProject/BasicConnectors alongside
 * its connector). Deliberately game-agnostic, same as the normalizer it
 * replaces in this test suite.
 */
class FixturePanelStatusNormalizer implements NormalizerContract
{
    public function normalize(array $raw, array $config = []): CapabilityValue
    {
        if (! isset($raw['state'])) {
            return CapabilityValue::unavailable($this->capability());
        }

        return CapabilityValue::ok($this->capability(), [
            'online' => $raw['state'] === 'running',
            'memory_bytes' => $raw['memory_bytes'] ?? null,
            'cpu_percent' => $raw['cpu_percent'] ?? null,
        ]);
    }

    public function capability(array $config = []): string
    {
        return 'server-status';
    }
}
