<?php

namespace App\Capabilities;

use App\Capabilities\Providers\ConnectorBackedProvider;
use App\Models\ConnectorInstance;
use GamingHub\Core\Capabilities\CapabilityRouter;
use GamingHub\Core\Capabilities\CapabilityValue;
use GamingHub\Core\Models\CapabilityBinding;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use GamingHub\Core\Normalizers\NormalizerRegistry;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Throwable;

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
        protected ConnectorBackedProvider $connectorProvider,
        protected ServerFieldMapper $fieldMapper,
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

                $provider->update([
                    'status' => $value->isOk() ? 'connected' : 'error',
                    'error_message' => $value->isOk() ? null : $value->error,
                    'last_check' => now(),
                ]);

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
     * Probes exactly one Provider in isolation, outside the merged
     * priority-stack walk probe() does — for the "Test" action in
     * ProvidersRelationManager, where an admin wants to know right now
     * whether *this* row works, not the server's overall merged status.
     * Persists status/error_message/last_check the same way a real poll
     * tick would, so a manual test and the background poller always leave
     * the row in a consistent state.
     */
    public function testProvider(Provider $provider): CapabilityValue
    {
        $capability = $this->capabilityFor($provider);

        $value = $capability
            ? ($this->probeProvider($provider, $capability)
                ?? CapabilityValue::unavailable($capability, 'Provider is not configured to answer any capability.'))
            : CapabilityValue::unavailable('unknown', 'Provider is missing a valid capability/normalizer configuration.');

        $this->persistProviderResult($provider, $value->isOk(), $value->error);

        return $value;
    }

    /**
     * The richer sibling of testProvider() — for the debug panel, where an
     * admin wants to see *where* in the pipeline a value came from (or a
     * failure happened), not just the final merged result. Fetches the raw
     * connector payload separately from normalizing it (one network call,
     * not two — see ConnectorBackedProvider::fetchRaw()), and captures
     * whatever gets logged during that one call via a temporary Monolog
     * handler rather than grepping the shared log file: this runs
     * synchronously in the same request, so an in-process capture is both
     * simpler and immune to interleaving with other concurrent requests or
     * the separate scheduler process (which this deliberately does not
     * show logs from — this is "what did clicking Test just do", not a
     * general log viewer).
     */
    public function debugTestProvider(Provider $provider): ProviderTestResult
    {
        $capability = $this->capabilityFor($provider);
        $connectorInstance = $provider->connector_instance_id
            ? ConnectorInstance::find($provider->connector_instance_id)
            : null;

        if (! $capability) {
            $error = 'Provider is missing a valid capability/normalizer configuration.';
            $this->persistProviderResult($provider, false, $error);

            return new ProviderTestResult(
                provider: $provider,
                connectorInstance: $connectorInstance,
                capability: null,
                raw: null,
                normalized: null,
                serverPreview: [],
                ok: false,
                error: $error,
                logs: [],
            );
        }

        $handler = new TestHandler;
        Log::getLogger()->pushHandler($handler);

        $raw = null;
        $normalized = null;
        $error = null;

        try {
            if ($provider->type === 'manual') {
                // No separate raw stage — the admin-entered value is both.
                $raw = $provider->config['value'] ?? [];
                $normalized = $raw;
            } else {
                $normalizerId = $provider->config['normalizer'] ?? null;
                $config = array_merge($provider->config ?? [], [
                    'connector_instance_id' => $provider->connector_instance_id,
                ]);

                $raw = $this->connectorProvider->fetchRaw($connectorInstance, $config['call'] ?? []);
                $normalized = $this->normalizers->get($normalizerId)->normalize($raw, $config)->data;
            }
        } catch (Throwable $e) {
            Log::warning('Provider debug test failed', [
                'provider_id' => $provider->id,
                'capability' => $capability,
                'error' => $e->getMessage(),
            ]);

            $error = $e->getMessage();
        } finally {
            Log::getLogger()->popHandler();
        }

        // Context is pretty-printed on its own indented line(s) rather than
        // appended inline — a single unbroken line (timestamp + message +
        // compact JSON context) is unreadable in a fixed-height panel and
        // painful to copy-paste out of.
        $logs = collect($handler->getRecords())->map(function ($record) {
            $line = sprintf('[%s] %s: %s', $record['datetime']->format('H:i:s.v'), $record['level_name'], $record['message']);

            if ($record['context']) {
                $line .= "\n".json_encode($record['context'], JSON_PRETTY_PRINT);
            }

            return $line;
        })->all();

        $ok = $error === null;

        $this->persistProviderResult($provider, $ok, $error);

        return new ProviderTestResult(
            provider: $provider,
            connectorInstance: $connectorInstance,
            capability: $capability,
            raw: $raw,
            normalized: $normalized,
            serverPreview: $ok ? $this->fieldMapper->map($normalized ?? []) : [],
            ok: $ok,
            error: $error,
            logs: $logs,
        );
    }

    protected function persistProviderResult(Provider $provider, bool $ok, ?string $error): void
    {
        $provider->update([
            'status' => $ok ? 'connected' : 'error',
            'error_message' => $ok ? null : $error,
            'last_check' => now(),
        ]);
    }

    protected function capabilityFor(Provider $provider): ?string
    {
        if ($provider->type === 'manual') {
            return $provider->config['capability'] ?? null;
        }

        $normalizerId = $provider->config['normalizer'] ?? null;

        if (! $normalizerId || ! $this->normalizers->has($normalizerId)) {
            return null;
        }

        return $this->normalizers->get($normalizerId)->capability($provider->config ?? []);
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
