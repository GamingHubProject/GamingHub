<?php

namespace App\Capabilities\Providers;

use App\Connectors\ConnectorRegistry;
use App\Models\ConnectorInstance;
use App\Models\InstalledPackage;
use GamingHub\Core\Capabilities\CapabilityValue;
use GamingHub\Core\Contracts\CapabilityProviderContract;
use GamingHub\Core\Models\CapabilityBinding;
use GamingHub\Core\Normalizers\NormalizerRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The bridge between Core's capability contract and Platform's Connectors —
 * this is Panel's actual work: call the connector, then hand the raw result
 * to Core's normalizer registry. Lives in Platform (not Core) specifically
 * because it's the one place allowed to touch a Connector.
 *
 * Binding's `value` JSON holds the connector config, e.g.:
 * {"connector_instance_id": 5, "call": {"endpoint": "/v1/api/metrics"}, "normalizer": "field-mapping", "capability": "server-status", "field_map": {"currentplayernum": "players"}}
 */
class ConnectorBackedProvider implements CapabilityProviderContract
{
    /**
     * Which InstalledPackage owns each package-provided normalizer. Checked
     * fresh on every resolution (not cached at boot) so disabling a package
     * actually stops its capabilities from resolving on the very next call —
     * this is what makes enable/disable real instead of a DB flag nothing
     * reads. A normalizer id with no entry here (e.g. 'field-mapping') is
     * always enabled — it's a generic, built-in normalizer, not owned by
     * any installable package, the same way Manual isn't either.
     */
    protected const PACKAGE_OWNED_NORMALIZERS = [
        'pelican-server-status' => 'pelican-connector',
    ];

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

        if (! $this->normalizerPackageIsEnabled($normalizerId)) {
            return CapabilityValue::unavailable($binding->capability);
        }

        try {
            $connector = $this->connectors->get($instance->type);
            $raw = $connector->fetch($instance, $config['call'] ?? []);

            return $this->normalizers->get($normalizerId)->normalize($raw, $config);
        } catch (Throwable $e) {
            Log::warning('Connector provider fetch failed', [
                'connector_instance_id' => $instance->id,
                'connector_type' => $instance->type,
                'capability' => $binding->capability,
                'error' => $e->getMessage(),
            ]);

            return CapabilityValue::unavailable($binding->capability, $e->getMessage());
        }
    }

    protected function normalizerPackageIsEnabled(string $normalizerId): bool
    {
        $ownerSlug = self::PACKAGE_OWNED_NORMALIZERS[$normalizerId] ?? null;

        if (! $ownerSlug) {
            return true;
        }

        try {
            return InstalledPackage::query()
                ->where('slug', $ownerSlug)
                ->where('status', 'enabled')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
