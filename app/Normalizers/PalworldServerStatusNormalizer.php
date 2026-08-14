<?php

namespace App\Normalizers;

use GamingHub\Core\Capabilities\CapabilityValue;
use GamingHub\Core\Contracts\NormalizerContract;

/**
 * Parses Palworld's own REST API — GET /v1/api/metrics — into the
 * "server-status" capability shape. Real response shape:
 * {"server_fps":30.0,"currentplayernum":3,"serverframetime":33.3,
 *  "maxplayernum":32,"uptime":12345,"days":1}
 */
class PalworldServerStatusNormalizer implements NormalizerContract
{
    public function normalize(array $raw): CapabilityValue
    {
        if (! isset($raw['maxplayernum'])) {
            return CapabilityValue::unavailable('server-status');
        }

        return CapabilityValue::ok('server-status', [
            'online' => true,
            'players' => $raw['currentplayernum'] ?? 0,
            'max_players' => $raw['maxplayernum'],
            'uptime' => $raw['uptime'] ?? null,
            'fps' => $raw['server_fps'] ?? null,
        ]);
    }
}
