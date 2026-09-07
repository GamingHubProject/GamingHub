<?php

namespace App\Profiles;

use App\Events\UserStatRecorded;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only supported way to write a user stat.
 *
 * One place owns the upsert, the uniqueness semantics and the key format,
 * so a feature recording play hours doesn't reinvent any of it and the
 * unique index doesn't become a source of race-condition errors at every
 * call site. Direct UserStat::create() calls are not an intended path.
 *
 * set() and increment() both exist and the distinction is load-bearing: a
 * poller reporting a cumulative total ("this account has 47 hours") must
 * set, or a retried poll inflates the number; an event stream reporting a
 * delta ("+2 hours since the last tick") must increment.
 */
class UserStats
{
    /**
     * A stat is addressed by (user, source, subject, key). `source`
     * namespaces the key, so a panel integration and a game integration
     * can both record 'hours' without overwriting one another.
     */
    private const IDENTIFIER = '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/';

    public static function set(
        User|int $user,
        string $source,
        string $key,
        float $value,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $metadata = null,
    ): UserStat {
        return self::write($user, $source, $key, $subjectType, $subjectId, $metadata, fn (?UserStat $existing) => [
            'value' => $value,
            'change' => $value - ($existing?->value ?? 0.0),
        ]);
    }

    public static function increment(
        User|int $user,
        string $source,
        string $key,
        float $by = 1.0,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $metadata = null,
    ): UserStat {
        return self::write($user, $source, $key, $subjectType, $subjectId, $metadata, fn (?UserStat $existing) => [
            'value' => ($existing?->value ?? 0.0) + $by,
            'change' => $by,
        ]);
    }

    public static function get(
        User|int $user,
        string $source,
        string $key,
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): ?UserStat {
        return self::query($user, $source, $key, $subjectType, $subjectId)->first();
    }

    /**
     * Shared read-modify-write. $resolve receives the current row (or null)
     * and returns the value to store plus the delta to report on the
     * event, which is the only thing set() and increment() actually
     * disagree about.
     *
     * SELECT ... FOR UPDATE inside a transaction serialises two writers
     * against the same stat. It cannot help when neither row exists yet —
     * there is nothing to lock — so the losing insert hits the unique
     * index instead; that one case retries, and the second attempt finds
     * the row and takes the lock. One retry is enough: after it, the row
     * provably exists.
     */
    private static function write(
        User|int $user,
        string $source,
        string $key,
        ?string $subjectType,
        ?int $subjectId,
        ?array $metadata,
        callable $resolve,
    ): UserStat {
        self::assertIdentifier($source, 'source');
        self::assertIdentifier($key, 'key');

        if (($subjectType === null) !== ($subjectId === null)) {
            throw new InvalidArgumentException('A stat subject needs both a type and an id, or neither.');
        }

        if ($subjectType !== null) {
            self::assertIdentifier($subjectType, 'subject type');
        }

        $userId = $user instanceof User ? $user->id : $user;

        $attempt = fn () => DB::transaction(function () use ($userId, $source, $key, $subjectType, $subjectId, $metadata, $resolve) {
            $existing = self::query($userId, $source, $key, $subjectType, $subjectId)->lockForUpdate()->first();
            ['value' => $value, 'change' => $change] = $resolve($existing);

            if ($existing) {
                $existing->value = $value;

                if ($metadata !== null) {
                    $existing->metadata = $metadata;
                }

                $existing->save();

                return [$existing, $change];
            }

            $created = UserStat::create([
                'user_id' => $userId,
                'source' => $source,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'key' => $key,
                'value' => $value,
                'metadata' => $metadata,
            ]);

            return [$created, $change];
        });

        try {
            [$stat, $change] = $attempt();
        } catch (QueryException $e) {
            [$stat, $change] = $attempt();
        }

        UserStatRecorded::dispatch($stat, $change);

        return $stat;
    }

    private static function query(
        User|int $user,
        string $source,
        string $key,
        ?string $subjectType,
        ?int $subjectId,
    ) {
        return UserStat::query()
            ->where('user_id', $user instanceof User ? $user->id : $user)
            ->where('source', $source)
            ->where('key', $key)
            // whereNull rather than where(..., null) — the latter builds
            // "= NULL", which matches nothing in Postgres, and a site-wide
            // stat would then be inserted fresh on every single write.
            ->when($subjectType === null, fn ($q) => $q->whereNull('subject_type')->whereNull('subject_id'))
            ->when($subjectType !== null, fn ($q) => $q->where('subject_type', $subjectType)->where('subject_id', $subjectId));
    }

    private static function assertIdentifier(string $value, string $label): void
    {
        if (! preg_match(self::IDENTIFIER, $value) || strlen($value) > 64) {
            throw new InvalidArgumentException("A stat {$label} must be lowercase a-z0-9 separated by . _ or -, at most 64 characters; got \"{$value}\".");
        }
    }
}
