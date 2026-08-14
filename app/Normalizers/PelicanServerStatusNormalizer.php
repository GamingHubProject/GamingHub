<?php

namespace App\Normalizers;

use GamingHub\Core\Capabilities\CapabilityValue;
use GamingHub\Core\Contracts\NormalizerContract;

/**
 * Parses Pelican's Client API — GET /api/client/servers/{id}/resources —
 * into the "server-status" capability shape. Real response shape:
 * {"object":"stats","attributes":{"current_state":"running","is_suspended":false,
 *  "resources":{"memory_bytes":123,"cpu_absolute":4.5,"disk_bytes":456,
 *  "network_rx_bytes":0,"network_tx_bytes":0,"uptime":12345}}}
 *
 * Game-agnostic: Pelican reports process-level stats regardless of which
 * game runs on the server, so this normalizer isn't Palworld-specific.
 */
class PelicanServerStatusNormalizer implements NormalizerContract
{
    public function normalize(array $raw): CapabilityValue
    {
        $attributes = $raw['attributes'] ?? null;

        if (! is_array($attributes) || ! isset($attributes['current_state'])) {
            return CapabilityValue::unavailable('server-status');
        }

        $resources = $attributes['resources'] ?? [];

        return CapabilityValue::ok('server-status', [
            'online' => $attributes['current_state'] === 'running',
            'memory_bytes' => $resources['memory_bytes'] ?? null,
            'cpu_percent' => $resources['cpu_absolute'] ?? null,
            'uptime' => $resources['uptime'] ?? null,
        ]);
    }
}
