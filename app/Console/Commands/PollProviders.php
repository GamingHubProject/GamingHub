<?php

namespace App\Console\Commands;

use App\Capabilities\CapabilityGateway;
use App\Capabilities\ServerAllocationSyncer;
use App\Capabilities\ServerFieldMapper;
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

    public function handle(CapabilityGateway $gateway, ServerFieldMapper $mapper, ServerAllocationSyncer $allocationSyncer): int
    {
        $this->info('Provider polling started.');

        do {
            $this->tick($gateway, $mapper, $allocationSyncer);

            if (! $this->option('once')) {
                sleep(1);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    protected function tick(CapabilityGateway $gateway, ServerFieldMapper $mapper, ServerAllocationSyncer $allocationSyncer): void
    {
        $dueConnectors = ConnectorInstance::all()->filter->isDueForPoll();

        // Manual providers have no connector_instance_id and no poll
        // interval to wait on — there's no external I/O to throttle, so
        // they're still considered here on every tick rather than tracked
        // by ConnectorInstance::isDueForPoll(). Without this, a server with
        // only a manual provider is never reachable through
        // connector_instance_id at all and its Server columns would never
        // update. Provider::isDueForPoll() (respectCadence below) still
        // applies on top of this — a manual provider's own
        // polling_cadence_seconds now throttles how often its value is
        // re-applied, same as a connector-backed one.
        $serverIds = Provider::where('type', 'manual')
            ->orWhereIn('connector_instance_id', $dueConnectors->pluck('id'))
            ->pluck('server_id')
            ->unique();

        foreach ($serverIds as $serverId) {
            $this->refreshServer($gateway, $mapper, $allocationSyncer, $serverId);
        }

        foreach ($dueConnectors as $connector) {
            $connector->update(['last_polled_at' => now()]);
        }
    }

    protected function refreshServer(CapabilityGateway $gateway, ServerFieldMapper $mapper, ServerAllocationSyncer $allocationSyncer, int $serverId): void
    {
        $server = Server::find($serverId);

        if (! $server) {
            return;
        }

        // If every one of this server's providers is mid-cadence (none due
        // yet), probe(respectCadence: true) would skip all of them and
        // come back UNAVAILABLE — indistinguishable, from the merge
        // result alone, from every provider having genuinely failed. That
        // would wrongly mark a healthy server offline just because it
        // wasn't its turn to be checked yet. Skip the tick entirely
        // instead, leaving the Server row exactly as it was.
        $hasDueProvider = Provider::where('server_id', $serverId)
            ->get()
            ->contains(fn (Provider $provider) => $provider->isDueForPoll());

        if (! $hasDueProvider) {
            return;
        }

        $value = $gateway->probe('server-status', $server, respectCadence: true);

        if (! $value->isOk()) {
            $server->update(['status' => 'offline', 'last_polled_at' => now()]);

            return;
        }

        $server->update([...$mapper->map($value->data), 'last_polled_at' => now()]);

        if (array_key_exists('allocations', $value->data)) {
            $allocationSyncer->sync($server, $value->data['allocations']);
        }
    }
}
