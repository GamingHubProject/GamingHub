<?php

namespace App\Connectors;

use App\Models\ConnectorInstance;
use RuntimeException;

/**
 * Generic authenticated REST caller — game-agnostic. Auth style is read
 * from the instance's credentials: {"username","password"} for HTTP Basic
 * (e.g. Palworld's REST API), or {"token"} for a Bearer token.
 */
class RestConnector implements ConnectorContract
{
    public function __construct(
        protected HttpRequestContract $http,
    ) {}

    public static function type(): string
    {
        return 'rest';
    }

    public function fetch(ConnectorInstance $instance, array $callConfig): array
    {
        $endpoint = $callConfig['endpoint'] ?? '/';
        $method = strtoupper($callConfig['method'] ?? 'GET');
        $url = rtrim($instance->base_url, '/').'/'.ltrim($endpoint, '/');

        $headers = ['Accept' => 'application/json'];
        $credentials = $instance->credentials ?? [];

        if (isset($credentials['username'], $credentials['password'])) {
            $headers['Authorization'] = 'Basic '.base64_encode("{$credentials['username']}:{$credentials['password']}");
        } elseif (isset($credentials['token'])) {
            $headers['Authorization'] = "Bearer {$credentials['token']}";
        }

        $response = $this->http->request($method, $url, $headers);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException("REST call to [{$url}] returned HTTP {$response['status']}.");
        }

        $data = json_decode($response['body'], true);

        if (! is_array($data)) {
            throw new RuntimeException("REST call to [{$url}] did not return valid JSON.");
        }

        return $data;
    }
}
