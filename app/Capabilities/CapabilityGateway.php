<?php

namespace App\Capabilities;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;

/**
 * The single entry point Hub Extensions use to read a capability. They never
 * know or care whether the value came from REST, RCON, a database, or a
 * manually-entered admin value — that's CapabilityRouter's job.
 *
 * inspect() is metadata-only and never triggers a live fetch. probe() is an
 * explicit runtime call that always hits the provider. get() is the normal
 * convenience path: return a fresh cached value if there is one, otherwise
 * probe.
 */
class CapabilityGateway
{
    /** Seconds a cached value is considered fresh before inspect() calls it STALE. */
    protected const FRESHNESS_SECONDS = 60;

    public function __construct(
        protected CapabilityRouter $router,
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

        $binding = $this->router->findBinding($capability, $subject);

        if (! $binding) {
            return CapabilityValue::unsupported($capability);
        }

        // Bound (supported) but no cached value exists — inspect() never
        // fetches, so a value that hasn't been probed yet is indistinguishable
        // from one whose provider is currently down: both are UNAVAILABLE.
        return CapabilityValue::unavailable($capability);
    }

    public function probe(string $capability, Model $subject): CapabilityValue
    {
        $binding = $this->router->findBinding($capability, $subject);

        if (! $binding) {
            $value = CapabilityValue::unsupported($capability);
            $this->writeCache($capability, $subject, $value);

            return $value;
        }

        $provider = $this->router->providerFor($binding->provider);
        $value = $provider->fetch($binding);

        $this->writeCache($capability, $subject, $value);

        return $value;
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
