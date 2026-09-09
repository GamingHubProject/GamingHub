<?php

namespace App\Contracts;

/**
 * One stat a game extension extracted from a raw API response for a
 * specific player. The extension returns raw player IDs — the platform
 * resolves them to Gaming Hub user IDs via HMAC lookup and writes the
 * stats through UserStats.
 */
final class PlayerStatWrite
{
    public function __construct(
        public readonly string $rawPlayerId,
        public readonly string $source,
        public readonly string $key,
        public readonly float $value,
        public readonly bool $cumulative = true,
        public readonly ?string $subjectType = null,
        public readonly ?int $subjectId = null,
        public readonly ?array $metadata = null,
    ) {}
}
