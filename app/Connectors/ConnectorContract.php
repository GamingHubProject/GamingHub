<?php

namespace App\Connectors;

use App\Models\ConnectorInstance;

/**
 * How Panel actually speaks to an external system. Returns raw data only —
 * never normalizes (that's Core's job, given the raw payload afterward).
 * Implementations know HOW to call something (auth, endpoint shape); they
 * never know WHAT the data means for a particular game.
 */
interface ConnectorContract
{
    public static function type(): string;

    /**
     * @param  array<string, mixed>  $callConfig  per-binding call details (e.g. endpoint, method)
     * @return array<mixed> raw, unnormalized payload
     */
    public function fetch(ConnectorInstance $instance, array $callConfig): array;
}
