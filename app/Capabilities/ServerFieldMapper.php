<?php

namespace App\Capabilities;

/**
 * The single place that knows how a normalized 'server-status' payload
 * (CapabilityValue->data) maps onto Server's own columns. Used by both
 * PollProviders (writes it for real) and the provider-test debug panel
 * (previews it without writing anything) — extracted specifically so
 * those two can never drift apart on what a given payload would produce.
 *
 * '*_percent_of_limit' keys map to Server's '*_percent' columns —
 * deliberately different names on the two sides: 'cpu_percent' as a
 * capability data key already meant "current usage as a percent of one
 * core" (maps to the legacy cpu_usage_percent column) before this class
 * also needed to express "percent of this server's own allocated limit"
 * (the new cpu_percent column) — two different metrics that would
 * otherwise collide under one name.
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

        if (array_key_exists('server_status', $data)) {
            $updates['status'] = $data['server_status'];
        } elseif (array_key_exists('online', $data)) {
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
        if (array_key_exists('cpu_current', $data)) {
            $updates['cpu_current'] = $data['cpu_current'];
        }
        if (array_key_exists('cpu_limit', $data)) {
            $updates['cpu_limit'] = $data['cpu_limit'];
        }
        if (array_key_exists('cpu_percent_of_limit', $data)) {
            $updates['cpu_percent'] = $data['cpu_percent_of_limit'];
        }
        if (array_key_exists('memory_current', $data)) {
            $updates['memory_current'] = $data['memory_current'];
        }
        if (array_key_exists('memory_limit', $data)) {
            $updates['memory_limit'] = $data['memory_limit'];
        }
        if (array_key_exists('memory_percent_of_limit', $data)) {
            $updates['memory_percent'] = $data['memory_percent_of_limit'];
        }
        if (array_key_exists('disk_current', $data)) {
            $updates['disk_current'] = $data['disk_current'];
        }
        if (array_key_exists('disk_limit', $data)) {
            $updates['disk_limit'] = $data['disk_limit'];
        }
        if (array_key_exists('disk_percent_of_limit', $data)) {
            $updates['disk_percent'] = $data['disk_percent_of_limit'];
        }
        if (array_key_exists('network_rx', $data)) {
            $updates['network_rx'] = $data['network_rx'];
        }
        if (array_key_exists('network_tx', $data)) {
            $updates['network_tx'] = $data['network_tx'];
        }
        if (array_key_exists('node_name', $data)) {
            $updates['node_name'] = $data['node_name'];
        }

        return $updates;
    }
}
