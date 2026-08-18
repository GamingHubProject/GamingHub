<?php

namespace App\Capabilities;

/**
 * Presentation-only mapping from a Server::$status value to a label and
 * Filament badge color — a plain colored-background badge, no leading
 * emoji/dot (Filament's own badge styling already carries the color
 * signal). Deliberately covers more values than Pelican alone reports
 * (online/offline/maintenance are the legacy pre-Pelican values a manual
 * provider or a future non-Pelican connector can still set) plus every
 * real ContainerStatus/ServerState value
 * PelicanServerStatusNormalizer::resolveServerStatus() can produce beyond
 * the ones originally asked for (restarting/exited/paused/dead/removing/
 * missing/install_failed/reinstall_failed/restoring_backup/created) — an
 * unmapped value would otherwise render blank rather than something
 * sensible.
 */
class ServerStatusBadge
{
    /** @var array<string, array{0: string, 1: string}> [label, Filament color] */
    protected const MAP = [
        'running' => ['Running', 'success'],
        'starting' => ['Starting', 'warning'],
        'stopping' => ['Stopping', 'warning'],
        'restarting' => ['Restarting', 'warning'],
        'offline' => ['Stopped', 'gray'],
        'exited' => ['Exited', 'danger'],
        'dead' => ['Dead', 'danger'],
        'missing' => ['Missing', 'danger'],
        'paused' => ['Paused', 'warning'],
        'removing' => ['Removing', 'warning'],
        'created' => ['Created', 'gray'],
        'suspended' => ['Suspended', 'danger'],
        'installing' => ['Installing', 'warning'],
        'install_failed' => ['Install failed', 'danger'],
        'reinstall_failed' => ['Reinstall failed', 'danger'],
        'restoring_backup' => ['Restoring backup', 'warning'],
        'transferring' => ['Transferring', 'warning'],
        'node_maintenance' => ['Node maintenance', 'warning'],
        // Legacy values — pre-Pelican-precedence manual providers and
        // non-Pelican connectors can still set these.
        'online' => ['Online', 'success'],
        'maintenance' => ['Maintenance', 'warning'],
    ];

    protected const FALLBACK = ['Unknown', 'gray'];

    public static function label(?string $status): string
    {
        return self::entry($status)[0];
    }

    public static function color(?string $status): string
    {
        return self::entry($status)[1];
    }

    /** @return array{0: string, 1: string} */
    protected static function entry(?string $status): array
    {
        return self::MAP[$status] ?? self::FALLBACK;
    }
}
