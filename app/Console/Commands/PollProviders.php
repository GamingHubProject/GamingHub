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

        $this->refreshServerStatus($gateway, $mapper, $allocationSyncer, $server);
        $this->refreshPlayerList($gateway, $mapper, $server);
    }

    /**
     * Gated on hasDueProviderFor('server-status', ...) specifically, not
     * "is any provider on this server due at all" — a server can carry
     * providers for other capabilities (player-list) with their own,
     * independent cadence. Gating on "any provider due" meant an unrelated
     * capability's provider becoming due was enough to trigger this probe
     * while the actual server-status provider (e.g. Pelican) wasn't ready
     * yet, so probe(respectCadence: true) legitimately came back
     * UNAVAILABLE — indistinguishable, from the merge result alone, from
     * every server-status provider having genuinely failed — and this then
     * wrongly wrote 'offline' over a server that was actually fine.
     */
    protected function refreshServerStatus(CapabilityGateway $gateway, ServerFieldMapper $mapper, ServerAllocationSyncer $allocationSyncer, Server $server): void
    {
        if (! $gateway->hasDueProviderFor('server-status', $server)) {
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

    /**
     * Same per-capability due-check as refreshServerStatus, so this can't
     * reintroduce the same bug in reverse (a server-status provider's
     * cadence firing must never force a player-list write either). Unlike
     * server-status, there's no "offline"-equivalent to force on failure —
     * an unanswered player-list probe just leaves current_players/
     * max_players exactly as they were, since there's no meaningful
     * "definitely no players" fallback the way "no status data" maps to
     * "treat as offline".
     *
     * Parsing a player count out of Pelican's own egg variables
     * (MAX_PLAYERS or similar) isn't done here — Pelican has no
     * connector/normalizer support for this capability yet, so a Manual
     * provider is the only thing that can currently answer it. That's
     * deliberate for now, not a gap in this method.
     */
    protected function refreshPlayerList(CapabilityGateway $gateway, ServerFieldMapper $mapper, Server $server): void
    {
        if (! $gateway->hasDueProviderFor('player-list', $server)) {
            return;
        }

        $value = $gateway->probe('player-list', $server, respectCadence: true);

        if (! $value->isOk()) {
            return;
        }

        $updates = $mapper->map($value->data);

        if ($updates !== []) {
            $server->update($updates);
        }
    }
}
