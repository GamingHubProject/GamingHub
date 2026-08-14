<?php

namespace App\Capabilities;

use Carbon\Carbon;

/**
 * Normalized result of asking the Capability Gateway for a value. Never
 * carries raw provider payloads — only the shape a Hub Extension expects.
 */
class CapabilityValue
{
    public const OK = 'ok';

    public const UNSUPPORTED = 'unsupported';

    public const UNAVAILABLE = 'unavailable';

    public const STALE = 'stale';

    public function __construct(
        public readonly string $capability,
        public readonly string $status,
        public readonly array $data = [],
        public readonly ?Carbon $resolvedAt = null,
    ) {}

    public static function ok(string $capability, array $data, ?Carbon $resolvedAt = null): self
    {
        return new self($capability, self::OK, $data, $resolvedAt ?? now());
    }

    public static function unsupported(string $capability): self
    {
        return new self($capability, self::UNSUPPORTED);
    }

    public static function unavailable(string $capability): self
    {
        return new self($capability, self::UNAVAILABLE);
    }

    public static function stale(string $capability, array $data, Carbon $resolvedAt): self
    {
        return new self($capability, self::STALE, $data, $resolvedAt);
    }

    public function isOk(): bool
    {
        return $this->status === self::OK;
    }

    public function toArray(): array
    {
        return [
            'capability' => $this->capability,
            'status' => $this->status,
            'data' => $this->data,
            'resolved_at' => $this->resolvedAt?->toIso8601String(),
        ];
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['capability'],
            $payload['status'],
            $payload['data'] ?? [],
            isset($payload['resolved_at']) ? Carbon::parse($payload['resolved_at']) : null,
        );
    }
}
