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
            $raw = $this->fetchRaw($instance, $config['call'] ?? []);

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

    /**
     * Just the connector I/O step, no binding-level validation (enabled/
     * instance-exists/normalizer-exists/package-enabled) — those stay in
     * fetch(). For callers that already know their binding is well-formed
     * (the provider-test debug panel) and want the raw payload *before*
     * normalization, without duplicating fetch()'s checks or triggering a
     * second network call to see both.
     */
    public function fetchRaw(ConnectorInstance $instance, array $callConfig): array
    {
        return $this->connectors->get($instance->type)->fetch($instance, $callConfig);
    }

    /**
     * Ownership isn't a hardcoded id => slug map anymore — it's read
     * straight off whichever installed package's own connector.json
     * declares this normalizer id, the same manifest PackageLoader already
     * parses to register it in the first place. A normalizer no package
     * declares (e.g. 'field-mapping', Core's generic built-in) is always
     * enabled. Checked fresh on every resolution (not cached) so disabling
     * a package actually stops its normalizers from resolving on the very
     * next call — what makes enable/disable real instead of a DB flag
     * nothing reads.
     */
    protected function normalizerPackageIsEnabled(string $normalizerId): bool
    {
        try {
            $ownerSlug = $this->findOwningPackageSlug($normalizerId);
        } catch (Throwable) {
            return false;
        }

        if (! $ownerSlug) {
            return true;
        }

        return InstalledPackage::query()
            ->where('slug', $ownerSlug)
            ->where('status', 'enabled')
            ->exists();
    }

    protected function findOwningPackageSlug(string $normalizerId): ?string
    {
        foreach (InstalledPackage::all() as $package) {
            $manifestPath = storage_path("app/packages/{$package->slug}/connector.json");

            if (! is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (isset($manifest['normalizers'][$normalizerId])) {
                return $package->slug;
            }
        }

        return null;
    }
}
