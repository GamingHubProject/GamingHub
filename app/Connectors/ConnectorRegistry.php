<?php

namespace App\Connectors;

use App\Manager\PackageLoader;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The one registry of Connector implementations, keyed by ConnectorInstance
 * type. Registered by class-string and resolved via the container on
 * demand (not eagerly at boot) so runtime rebindings — e.g. a fake
 * HttpRequestContract in tests — actually take effect.
 *
 * A type not already registered triggers one PackageLoader scan before
 * giving up (not eagerly, and not cached past the miss) — Connector
 * packages are enabled/disabled at runtime, and AppServiceProvider::boot()
 * only runs once per request, before whatever code path ends up calling
 * get() ever executes, so "scan once at boot" would miss any package
 * (en/dis)abled after boot but within the same request/test (an
 * InstalledPackage row created mid-test, for instance). Once a type is
 * found it's cached in $connectors for the rest of this instance's life,
 * same as before.
 */
class ConnectorRegistry
{
    /** @var array<string, class-string<ConnectorContract>> */
    protected array $connectors = [];

    public function __construct(protected Container $container) {}

    /**
     * @param  class-string<ConnectorContract>  $connectorClass
     */
    public function register(string $connectorClass): void
    {
        $this->connectors[$connectorClass::type()] = $connectorClass;
    }

    public function get(string $type): ConnectorContract
    {
        if (! isset($this->connectors[$type])) {
            $this->container->make(PackageLoader::class)->loadConnectorPackages();
        }

        $class = $this->connectors[$type]
            ?? throw new InvalidArgumentException("No connector registered for type [{$type}].");

        return $this->container->make($class);
    }
}
