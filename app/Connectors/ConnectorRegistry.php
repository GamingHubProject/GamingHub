<?php

namespace App\Connectors;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The one registry of Connector implementations, keyed by ConnectorInstance
 * type. Registered by class-string and resolved via the container on
 * demand (not eagerly at boot) so runtime rebindings — e.g. a fake
 * HttpRequestContract in tests — actually take effect.
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
        $class = $this->connectors[$type]
            ?? throw new InvalidArgumentException("No connector registered for type [{$type}].");

        return $this->container->make($class);
    }
}
