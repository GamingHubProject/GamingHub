<?php

namespace App\Contracts;

use GamingHub\Core\Models\Server;

/**
 * Optional companion to GameExtensionContract — a game extension that can
 * match in-game player IDs to Gaming Hub users and extract per-player
 * stats from raw API responses.
 *
 * Not every game extension does player identity (some are server-management
 * only), so this is a separate interface checked with instanceof rather
 * than a set of stubs on the base contract.
 */
interface PlayerStatsContract
{
    /**
     * Which game slugs this extension handles identity for.
     * Drives the linking UI — only these slugs appear in the list.
     *
     * @return list<string>
     */
    public function supportsPlayerIdentity(): array;

    /**
     * Label shown to the user for the ID input field.
     * "Steam ID", "Player Name", "Epic Account ID", etc.
     */
    public function playerIdLabel(string $gameSlug): string;

    /**
     * Validate the format before hashing. Return null if valid,
     * or an error message string if not.
     */
    public function validatePlayerId(string $gameSlug, string $rawPlayerId): ?string;

    /**
     * Called by the poll pipeline with the server being polled and the
     * raw API response the connector already fetched. Returns stat
     * writes to apply — the platform resolves raw player IDs to users
     * and writes the stats.
     *
     * @return list<PlayerStatWrite>
     */
    public function extractPlayerStats(Server $server, array $rawApiResponse): array;
}
