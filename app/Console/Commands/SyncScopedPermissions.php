<?php

namespace App\Console\Commands;

use App\Models\ServerGroup;
use App\Permissions\ScopedPermissionGenerator;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Console\Command;

/**
 * Backfill for rows that already existed before GameObserver/
 * ServerGroupObserver/ServerObserver were wired up — those only fire on
 * new creates, so any Game/ServerGroup/Server already in the database
 * before this feature shipped needs its 4 scoped permissions generated
 * here instead. Safe to re-run any time: ScopedPermissionGenerator uses
 * firstOrCreate, so nothing is duplicated for rows already covered.
 */
class SyncScopedPermissions extends Command
{
    protected $signature = 'permissions:sync-scoped';

    protected $description = 'Generate the 4 scoped permissions (settings/page/news/player) for every Game, ServerGroup, and Server that is missing them.';

    public function handle(ScopedPermissionGenerator $generator): int
    {
        $counts = [
            'game' => 0,
            'servergroup' => 0,
            'server' => 0,
        ];

        foreach (Game::all() as $game) {
            $generator->generateFor($game, 'game');
            $counts['game']++;
        }

        foreach (ServerGroup::all() as $serverGroup) {
            $generator->generateFor($serverGroup, 'servergroup');
            $counts['servergroup']++;
        }

        foreach (Server::all() as $server) {
            $generator->generateFor($server, 'server');
            $counts['server']++;
        }

        $this->info(sprintf(
            'Synced scoped permissions for %d games, %d server groups, %d servers.',
            $counts['game'],
            $counts['servergroup'],
            $counts['server'],
        ));

        return self::SUCCESS;
    }
}
