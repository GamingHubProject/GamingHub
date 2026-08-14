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
     * Configuration schema in the same shape stored on games.configuration_schema:
     * a map of setting name to {type, min, max, default, requiresRestart, description}.
     *
     * @return array<string, array<string, mixed>>
     */
    public function configurationSchema(): array;
}
