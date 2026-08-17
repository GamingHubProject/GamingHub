<?php

namespace App\Capabilities;

/**
 * The single place that knows how a normalized 'server-status' payload
 * (CapabilityValue->data — online/players/max_players/cpu_percent/
 * memory_bytes) maps onto Server's own columns. Used by both
 * PollProviders (writes it for real) and the provider-test debug panel
 * (previews it without writing anything) — extracted specifically so
 * those two can never drift apart on what a given payload would produce.
 */
class ServerFieldMapper
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> Server column => value, only for keys present in $data
     */
    public function map(array $data): array
    {
        $updates = [];

        if (array_key_exists('online', $data)) {
            $updates['status'] = $data['online'] ? 'online' : 'offline';
        }
        if (array_key_exists('players', $data)) {
            $updates['current_players'] = $data['players'];
        }
        if (array_key_exists('max_players', $data)) {
            $updates['max_players'] = $data['max_players'];
        }
        if (array_key_exists('cpu_percent', $data)) {
            $updates['cpu_usage_percent'] = $data['cpu_percent'];
        }
        if (array_key_exists('memory_bytes', $data)) {
            $updates['memory_usage_bytes'] = $data['memory_bytes'];
        }

        return $updates;
    }
}
