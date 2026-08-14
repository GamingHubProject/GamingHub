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

        $token = $instance->credentials['token']
            ?? throw new RuntimeException("Connector instance [{$instance->name}] has no Pelican API token configured.");

        $url = rtrim($instance->base_url, '/')."/api/client/servers/{$serverIdentifier}/resources";

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
