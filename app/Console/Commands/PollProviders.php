<?php

namespace App\Console\Commands;

use App\Capabilities\CapabilityGateway;
use App\Models\ConnectorInstance;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use Illuminate\Console\Command;

/**
 * Background auto-refresh — the piece that makes Provider.status and a
 * Server's live stats (players/cpu/memory/online) update on their own
 * instead of only when an admin views a page. Each ConnectorInstance sets
 * its own poll_interval_seconds; this loop wakes up every second and only
 * actually probes a connector once its own interval has elapsed, so several
 * connectors run on independent cadences from one process.
 *
 * Runs as its own long-lived container (see docker-compose*.yml, service
 * "scheduler") rather than through Laravel's schedule:run, since that only
 * fires once a minute — too coarse for a "refresh every N seconds" connector
 * setting.
 */
class PollProviders extends Command
{
    protected $signature = 'gaming-hub:poll-providers
        {--once : Run a single tick and exit, instead of looping forever (used by tests / manual runs)}';

    protected $description = "Continuously refresh Provider status and Server stats from their connectors, on each connector's own interval.";

    public function handle(CapabilityGateway $gateway): int
    {
        $this->info('Provider polling started.');

        do {
            $this->tick($gateway);

            if (! $this->option('once')) {
                sleep(1);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    protected function tick(CapabilityGateway $gateway): void
    {
        $dueConnectors = ConnectorInstance::all()->filter->isDueForPoll();

        if ($dueConnectors->isEmpty()) {
            return;
        }

        $serverIds = Provider::whereIn('connector_instance_id', $dueConnectors->pluck('id'))
            ->pluck('server_id')
            ->unique();

        foreach ($serverIds as $serverId) {
            $this->refreshServer($gateway, $serverId);
        }

        foreach ($dueConnectors as $connector) {
            $connector->update(['last_polled_at' => now()]);
        }
    }

    protected function refreshServer(CapabilityGateway $gateway, int $serverId): void
    {
        $server = Server::find($serverId);

        if (! $server) {
            return;
        }

        $value = $gateway->probe('server-status', $server);

        if (! $value->isOk()) {
            $server->update(['status' => 'offline', 'last_polled_at' => now()]);

            return;
        }

        $data = $value->data;

        $updates = ['last_polled_at' => now()];

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

        $server->update($updates);
    }
}
