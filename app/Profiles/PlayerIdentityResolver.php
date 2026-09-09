<?php

namespace App\Profiles;

use App\Models\PlayerGameIdentity;

/**
 * The platform-owned batch lookup that maps raw player IDs to Gaming Hub
 * user IDs. Extensions hand in raw IDs from a game API; this hashes each
 * one with the same HMAC key used at link time and looks them up in
 * player_game_identities.
 *
 * Extensions never see the HMAC key, never hash anything themselves, and
 * never touch the table directly.
 */
class PlayerIdentityResolver
{
    /**
     * @param  string          $gameSlug     The game the IDs belong to.
     * @param  list<string>    $rawPlayerIds Raw player IDs from the game API.
     * @return array<string, int|null>       rawId => userId (null = no linked user).
     */
    public static function resolve(string $gameSlug, array $rawPlayerIds): array
    {
        if ($rawPlayerIds === []) {
            return [];
        }

        $hashed = [];
        foreach ($rawPlayerIds as $rawId) {
            $hashed[$rawId] = self::hash($rawId);
        }

        $linked = PlayerGameIdentity::query()
            ->where('game_slug', $gameSlug)
            ->whereIn('hashed_player_id', array_values($hashed))
            ->pluck('user_id', 'hashed_player_id');

        $result = [];
        foreach ($hashed as $rawId => $hash) {
            $result[$rawId] = $linked[$hash] ?? null;
        }

        return $result;
    }

    public static function hash(string $rawPlayerId): string
    {
        return hash_hmac('sha256', $rawPlayerId, config('app.key'));
    }
}
