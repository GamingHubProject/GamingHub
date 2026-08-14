<?php

namespace App\Connectors;

use App\Models\ConnectorInstance;
use RuntimeException;

/**
 * Calls Pelican's Client API to discover/monitor a server. One panel
 * manages many servers, so callConfig must say which one via
 * "server_identifier" (Pelican's short server ID). Auth is a Client API
 * key: credentials = {"token": "..."}.
 */
class PelicanConnector implements ConnectorContract
{
    public function __construct(
        protected HttpRequestContract $http,
    ) {}

    public static function type(): string
    {
        return 'pelican';
    }

    public function fetch(ConnectorInstance $instance, array $callConfig): array
    {
        $serverIdentifier = $callConfig['server_identifier']
            ?? throw new RuntimeException('Pelican calls require a "server_identifier" in the binding config.');

        return $this->request($instance, "/api/client/servers/{$serverIdentifier}/resources");
    }

    /**
     * Lists every server this API key can see — GET /api/client. This is
     * the auto-discovery Pelican makes possible: an admin picks a server
     * identifier from a real list instead of typing one blind.
     *
     * @return array<int, array{identifier: string, name: string}>
     */
    public function listServers(ConnectorInstance $instance): array
    {
        $data = $this->request($instance, '/api/client');

        return collect($data['data'] ?? [])
            ->map(fn (array $entry) => [
                'identifier' => $entry['attributes']['identifier'] ?? '',
                'name' => $entry['attributes']['name'] ?? '(unnamed)',
            ])
            ->filter(fn (array $server) => $server['identifier'] !== '')
            ->values()
            ->all();
    }

    protected function request(ConnectorInstance $instance, string $path): array
    {
        $token = $instance->credentials['token']
            ?? throw new RuntimeException("Connector instance [{$instance->name}] has no Pelican API token configured.");

        $url = rtrim($instance->base_url, '/').'/'.ltrim($path, '/');

        $response = $this->http->request('GET', $url, [
            'Accept' => 'Application/vnd.pterodactyl.v1+json',
            'Authorization' => "Bearer {$token}",
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException("Pelican call to [{$url}] returned HTTP {$response['status']}.");
        }

        $data = json_decode($response['body'], true);

        if (! is_array($data)) {
            throw new RuntimeException("Pelican call to [{$url}] did not return valid JSON.");
        }

        return $data;
    }
}
