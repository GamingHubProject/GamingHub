<?php

namespace App\Contracts;

/**
 * What a Game Extension package must implement once real package loading
 * exists (see the Manager repo — package discovery/install/enable/disable
 * lives there, not here). Platform currently only tracks extensions in the
 * game_extensions table via this contract's shape; nothing implements or
 * loads this interface yet.
 */
interface GameExtensionContract
{
    /**
     * Stable machine identifier, e.g. "palworld-integration".
     */
    public function slug(): string;

    public function name(): string;

    public function version(): string;

    /**
     * Capability IDs this extension can provide (e.g. "player-positions",
     * "server-status") when bound to a Connector for a given Game.
     *
     * @return list<string>
     */
    public function capabilities(): array;

    /**
     * A map of setting name to {type, min, max, default, requiresRestart,
     * description}. Core no longer has a games.configuration_schema column
     * (removed — core had no consumer for it, and nothing pushed a
     * server's actual config anywhere) — this extension owns its settings
     * shape entirely; there's no core schema to match anymore.
     *
     * @return array<string, array<string, mixed>>
     */
    public function configurationSchema(): array;
}
