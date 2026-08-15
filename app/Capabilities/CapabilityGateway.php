<?php

namespace App\Capabilities;

use GamingHub\Core\Capabilities\CapabilityRouter;
use GamingHub\Core\Capabilities\CapabilityValue;
use GamingHub\Core\Models\CapabilityBinding;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use GamingHub\Core\Normalizers\NormalizerRegistry;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The single entry point Hub Extensions use to read a capability — Platform
 * acting as Panel. They never know or care whether the value came from
 * REST, RCON, a database, or a manually-entered admin value.
 *
 * For a Server, resolution walks its Providers as a priority stack (see
 * Provider.priority, admin-reorderable in ProvidersRelationManager — "REST
 * above Pelican above a Manual entry"): each provider is tried in order —
 * connector-backed or manual, dispatched via Provider.type — and fields are
 * merged so a lower-priority provider only fills gaps rather than
 * overwriting a field a higher-priority one already answered. A persisted
 * CapabilityBinding is not part of that stack and is never generated on a
 * Provider's behalf — it is one plain admin-entered "default" value sitting
 * below the whole provider stack, used only when no provider answers.
 *
 * inspect() is metadata-only and never triggers a live fetch. probe() is an
 * explicit runtime call that always hits providers. get() is the normal
 * convenience path: return a fresh cached value if there is one, otherwise
 * probe.
 */
class CapabilityGateway
{
    /** Seconds a cached value is considered fresh before inspect() calls it STALE. */
    protected const FRESHNESS_SECONDS = 60;

    public function __construct(
        protected CapabilityRouter $router,
        protected NormalizerRegistry $normalizers,
        protected Cache $cache,
    ) {}

    public function get(string $capability, Model $subject): CapabilityValue
    {
        $cached = $this->readCache($capability, $subject);

        if ($cached && $cached->resolvedAt && $cached->resolvedAt->diffInSeconds(now()) <= self::FRESHNESS_SECONDS) {
            return $cached;
        }

        return $this->probe($capability, $subject);
    }

    public function inspect(string $capability, Model $subject): CapabilityValue
    {
        $cached = $this->readCache($capability, $subject);

        if ($cached) {
            $isFresh = $cached->resolvedAt
                && $cached->resolvedAt->diffInSeconds(now()) <= self::FRESHNESS_SECONDS;

            return $isFresh
                ? $cached
                : CapabilityValue::stale($capability, $cached->data, $cached->resolvedAt);
        }

        if (! $this->hasSupport($capability, $subject)) {
            return CapabilityValue::unsupported($capability);
        }

        // Supported but no cached value exists — inspect() never fetches, so
        // a value that hasn't been probed yet is indistinguishable from one
        // whose provider is currently down: both are UNAVAILABLE.
        return CapabilityValue::unavailable($capability);
    }

    public function probe(string $capability, Model $subject): CapabilityValue
    {
        $mergedData = [];
        $anyOk = false;

        if ($subject instanceof Server) {
            foreach ($this->providerStack($subject) as $provider) {
                $value = $this->probeProvider($provider, $capability);

                if ($value === null) {
                    continue; // this provider's normalizer doesn't serve this capability at all
                }

                $provider->update(['status' => $value->isOk() ? 'connected' : 'error', 'last_check' => now()]);

                if ($value->isOk()) {
                    $anyOk = true;
                    $mergedData += $value->data;
                }
            }
        }

        // Bottom of the stack: one admin-entered "default", no I/O.
        $binding = $this->router->findBinding($capability, $subject);

        if ($binding) {
            $defaultValue = $this->router->providerFor($binding->provider)->fetch($binding);

            if ($defaultValue->isOk()) {
                $anyOk = true;
                $mergedData += $defaultValue->data;
            }
        }

        $merged = match (true) {
            $anyOk => CapabilityValue::ok($capability, $mergedData),
            $this->hasSupport($capability, $subject) => CapabilityValue::unavailable($capability),
            default => CapabilityValue::unsupported($capability),
        };

        $this->writeCache($capability, $subject, $merged);

        return $merged;
    }

    /**
     * @return Collection<int, Provider>
     */
    protected function providerStack(Server $server): Collection
    {
        return Provider::where('server_id', $server->id)->orderBy('priority')->orderBy('id')->get();
    }

    /**
     * Null means this provider is simply not relevant to the requested
     * capability — distinct from an UNAVAILABLE fetch failure, which still
     * counts as "tried". Dispatches on Provider.type: each type builds its
     * own in-memory CapabilityBinding (never persisted — this is the
     * priority stack itself, not the separate CapabilityBinding table) and
     * hands it to that type's registered CapabilityProviderContract, so
     * adding a third provider type later means one more match arm here,
     * not a rewrite of probe()/hasSupport().
     */
    protected function probeProvider(Provider $provider, string $capability): ?CapabilityValue
    {
        return match ($provider->type) {
            'manual' => $this->probeManualProvider($provider, $capability),
            default => $this->probeConnectorProvider($provider, $capability),
        };
    }

    protected function probeConnectorProvider(Provider $provider, string $capability): ?CapabilityValue
    {
        $normalizerId = $provider->config['normalizer'] ?? null;

        if (! $normalizerId || ! $this->normalizers->has($normalizerId)) {
            return null;
        }

        if ($this->normalizers->get($normalizerId)->capability($provider->config ?? []) !== $capability) {
            return null;
        }

        // The whole config flows through, not just connector_instance_id/
        // call/normalizer — a generic normalizer (FieldMappingNormalizer)
        // reads its own extra keys (capability, field_map) out of this same
        // config, so nothing here needs to know what a given normalizer
        // requires beyond what's already on the Provider row.
        $binding = new CapabilityBinding([
            'capability' => $capability,
            'provider' => 'connector',
            'enabled' => true,
            'value' => array_merge($provider->config ?? [], [
                'connector_instance_id' => $provider->connector_instance_id,
            ]),
        ]);

        return $this->router->providerFor('connector')->fetch($binding);
    }

    protected function probeManualProvider(Provider $provider, string $capability): ?CapabilityValue
    {
        if (($provider->config['capability'] ?? null) !== $capability) {
            return null;
        }

        $binding = new CapabilityBinding([
            'capability' => $capability,
            'provider' => 'manual',
            'enabled' => true,
            'value' => $provider->config['value'] ?? [],
        ]);

        return $this->router->providerFor('manual')->fetch($binding);
    }

    protected function hasSupport(string $capability, Model $subject): bool
    {
        if ($subject instanceof Server) {
            foreach ($this->providerStack($subject) as $provider) {
                if ($provider->type === 'manual') {
                    if (($provider->config['capability'] ?? null) === $capability) {
                        return true;
                    }

                    continue;
                }

                $normalizerId = $provider->config['normalizer'] ?? null;

                if ($normalizerId && $this->normalizers->has($normalizerId)
                    && $this->normalizers->get($normalizerId)->capability($provider->config ?? []) === $capability) {
                    return true;
                }
            }
        }

        return (bool) $this->router->findBinding($capability, $subject);
    }

    protected function readCache(string $capability, Model $subject): ?CapabilityValue
    {
        $payload = $this->cache->get($this->cacheKey($capability, $subject));

        return $payload ? CapabilityValue::fromArray($payload) : null;
    }

    protected function writeCache(string $capability, Model $subject, CapabilityValue $value): void
    {
        // Only OK values are worth caching as "fresh" — unsupported/unavailable
        // results are cheap to recompute and shouldn't linger past their cause.
        if (! $value->isOk()) {
            $this->cache->forget($this->cacheKey($capability, $subject));

            return;
        }

        $this->cache->put($this->cacheKey($capability, $subject), $value->toArray(), now()->addHours(6));
    }

    protected function cacheKey(string $capability, Model $subject): string
    {
        return sprintf('capability:%s:%s:%s', $capability, $subject->getMorphClass(), $subject->getKey());
    }
}
