<?php

namespace App\Capabilities;

use GamingHub\Core\Models\Server;
use GamingHub\Core\Models\ServerAllocation;

/**
 * Replaces a Server's allocations wholesale on every poll tick rather than
 * diffing/upserting — these are entirely connector-owned with no locally
 * editable state (unlike Provider rows, which admins configure directly),
 * so there's nothing worth preserving across a sync. Only called from a
 * real poll tick (PollProviders), same as ServerFieldMapper's output only
 * gets written there — a "Test" click previews normalized data without
 * touching the Server or its allocations.
 */
class ServerAllocationSyncer
{
    /**
     * @param  array<int, array{external_id: ?int, ip: string, ip_alias: ?string, port: ?int, is_default: bool, notes: ?string}>  $allocations
     */
    public function sync(Server $server, array $allocations): void
    {
        $server->allocations()->delete();

        foreach ($allocations as $allocation) {
            $server->allocations()->create($allocation);
        }
    }
}
