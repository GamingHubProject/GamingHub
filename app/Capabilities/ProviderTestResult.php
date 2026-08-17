<?php

namespace App\Capabilities;

use App\Models\ConnectorInstance;
use GamingHub\Core\Models\Provider;

/**
 * Bundles everything the provider-test debug panel shows, from one
 * CapabilityGateway::debugTestProvider() call. Raw, normalized, and the
 * Server-column preview are kept as three separate shapes rather than
 * flattened into one — the whole point of the panel is showing an admin
 * exactly where in the pipeline a value came from, or a failure happened.
 */
class ProviderTestResult
{
    /**
     * @param  array<string, mixed>|null  $raw  Null when the fetch never got far enough to produce one (misconfigured provider, or the connector call itself threw).
     * @param  array<string, mixed>|null  $normalized  Null under the same conditions as $raw, or if normalization itself failed.
     * @param  array<string, mixed>  $serverPreview  What ServerFieldMapper would write onto the Server row — never actually written by a test.
     * @param  list<string>  $logs  Formatted log lines emitted during this one test call (see Monolog\Handler\TestHandler usage in debugTestProvider()).
     */
    public function __construct(
        public readonly Provider $provider,
        public readonly ?ConnectorInstance $connectorInstance,
        public readonly ?string $capability,
        public readonly ?array $raw,
        public readonly ?array $normalized,
        public readonly array $serverPreview,
        public readonly bool $ok,
        public readonly ?string $error,
        public readonly array $logs,
    ) {}
}
