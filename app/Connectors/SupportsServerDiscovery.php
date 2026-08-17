<?php

namespace App\Connectors;

use App\Models\ConnectorInstance;

/**
 * Not every Connector can list every server on the remote panel — a
 * generic REST connector has no such concept, only Pelican-shaped panels
 * (and similar) expose an admin-scoped "list every server" endpoint. This
 * is deliberately a separate marker interface rather than a method on
 * ConnectorContract itself: PHP interfaces can't have optional/default
 * methods, so adding it to ConnectorContract directly would force every
 * connector — including ones that fundamentally can't support it — to
 * define a method that just returns nothing. Callers check
 * `instanceof SupportsServerDiscovery` before calling listServers() and
 * hide/disable discovery UI entirely for a connector that doesn't
 * implement it, rather than importing a specific connector class by name.
 */
interface SupportsServerDiscovery
{
    /**
     * Every server on the panel, regardless of owner.
     *
     * @return array<int, array{identifier: string, name: string}>
     */
    public function listServers(ConnectorInstance $instance): array;
}
