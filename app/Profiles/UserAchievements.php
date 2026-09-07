<?php

namespace App\Profiles;

use App\Events\UserAchievementAwarded;
use App\Models\User;
use App\Models\UserAchievement;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only supported way to award an achievement.
 *
 * award() is idempotent: awarding something a person already holds
 * returns the row they already have, leaves earned_at alone, and fires no
 * event. That matters because the natural caller is a listener reacting to
 * a stat ("hours crossed 100") which will fire again on the next tick, and
 * a badge that re-announces itself every hour is worse than no badge.
 *
 * `achievement_key` is a plain string rather than a foreign key: there are
 * no achievements to define yet, and a definitions table with no consumer
 * is speculative structure. A future table joins on
 * (source, achievement_key).
 */
class UserAchievements
{
    private const IDENTIFIER = '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/';

    public static function award(
        User|int $user,
        string $source,
        string $achievementKey,
        ?CarbonInterface $earnedAt = null,
        ?array $metadata = null,
    ): UserAchievement {
        self::assertIdentifier($source, 'source');
        self::assertIdentifier($achievementKey, 'achievement key');

        $userId = $user instanceof User ? $user->id : $user;

        $attempt = fn () => DB::transaction(function () use ($userId, $source, $achievementKey, $earnedAt, $metadata) {
            $existing = UserAchievement::query()
                ->where('user_id', $userId)
                ->where('source', $source)
                ->where('achievement_key', $achievementKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [$existing, false];
            }

            $created = UserAchievement::create([
                'user_id' => $userId,
                'source' => $source,
                'achievement_key' => $achievementKey,
                'earned_at' => $earnedAt ?? now(),
                'metadata' => $metadata,
            ]);

            return [$created, true];
        });

        try {
            [$achievement, $isNew] = $attempt();
        } catch (QueryException $e) {
            // Lost the insert race — the row exists now, and the retry
            // takes the "already held" branch above.
            [$achievement, $isNew] = $attempt();
        }

        if ($isNew) {
            UserAchievementAwarded::dispatch($achievement);
        }

        return $achievement;
    }

    /** Whether this person already holds it — for a caller that wants to
     *  avoid building expensive metadata it would then throw away. */
    public static function holds(User|int $user, string $source, string $achievementKey): bool
    {
        return UserAchievement::query()
            ->where('user_id', $user instanceof User ? $user->id : $user)
            ->where('source', $source)
            ->where('achievement_key', $achievementKey)
            ->exists();
    }

    public static function revoke(User|int $user, string $source, string $achievementKey): void
    {
        UserAchievement::query()
            ->where('user_id', $user instanceof User ? $user->id : $user)
            ->where('source', $source)
            ->where('achievement_key', $achievementKey)
            ->delete();
    }

    private static function assertIdentifier(string $value, string $label): void
    {
        if (! preg_match(self::IDENTIFIER, $value) || strlen($value) > 64) {
            throw new InvalidArgumentException("An achievement {$label} must be lowercase a-z0-9 separated by . _ or -, at most 64 characters; got \"{$value}\".");
        }
    }
}
