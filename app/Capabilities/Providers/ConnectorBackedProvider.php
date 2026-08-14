<?php

namespace App\Capabilities\Providers;

use App\Connectors\ConnectorRegistry;
use App\Models\ConnectorInstance;
use GamingHub\Core\Capabilities\CapabilityValue;
use GamingHub\Core\Contracts\CapabilityProviderContract;
use GamingHub\Core\Models\CapabilityBinding;
use GamingHub\Core\Normalizers\NormalizerRegistry;
use Throwable;

/**
 * The bridge between Core's capability contract and Platform's Connectors —
 * this is Panel's actual work: call the connector, then hand the raw result
 * to Core's normalizer registry. Lives in Platform (not Core) specifically
 * because it's the one place allowed to touch a Connector.
 *
 * Binding's `value` JSON holds the connector config:
 * {"connector_instance_id": 5, "call": {"endpoint": "/v1/api/metrics"}, "normalizer": "palworld-server-status"}
 */
class ConnectorBackedProvider implements CapabilityProviderContract
{
    public function __construct(
        protected ConnectorRegistry $connectors,
        protected NormalizerRegistry $normalizers,
    ) {}

    public static function id(): string
    {
        return 'connector';
    }

    public function fetch(CapabilityBinding $binding): CapabilityValue
    {
        if (! $binding->enabled) {
            return CapabilityValue::unavailable($binding->capability);
        }

        $config = $binding->value ?? [];

        $instance = ConnectorInstance::find($config['connector_instance_id'] ?? null);
        $normalizerId = $config['normalizer'] ?? null;

        if (! $instance || ! $normalizerId || ! $this->normalizers->has($normalizerId)) {
            return CapabilityValue::unavailable($binding->capability);
        }

        try {
            $connector = $this->connectors->get($instance->type);
            $raw = $connector->fetch($instance, $config['call'] ?? []);

            return $this->normalizers->get($normalizerId)->normalize($raw);
        } catch (Throwable) {
            return CapabilityValue::unavailable($binding->capability);
        }
    }
}
