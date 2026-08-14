<?php

namespace App\Experience;

use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use App\Models\Theme;

/**
 * Resolves the effective design tokens for a given scope by merging the
 * theme hierarchy: platform defaults, then game overrides, then server
 * overrides. Each level only needs to set the tokens it wants to change.
 */
class ThemeResolver
{
    public function resolve(?Game $game = null, ?Server $server = null): array
    {
        $tokens = [];

        $tokens = array_merge($tokens, $this->tokensFor(Theme::LEVEL_PLATFORM));

        if ($game) {
            $tokens = array_merge($tokens, $this->tokensFor(Theme::LEVEL_GAME, gameId: $game->id));
        }

        if ($server) {
            $tokens = array_merge($tokens, $this->tokensFor(Theme::LEVEL_SERVER, serverId: $server->id));
        }

        return $tokens;
    }

    protected function tokensFor(string $level, ?int $gameId = null, ?int $serverId = null): array
    {
        $theme = Theme::query()
            ->where('level', $level)
            ->when($gameId, fn ($query) => $query->where('game_id', $gameId))
            ->when($serverId, fn ($query) => $query->where('server_id', $serverId))
            ->orderByDesc('is_default')
            ->first();

        return $theme?->tokens ?? [];
    }
}
