<?php

namespace Tests\Unit\Connectors;

use App\Connectors\PelicanConnector;
use App\Models\ConnectorInstance;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\Connectors\Support\FakeHttpRequester;

class PelicanConnectorTest extends TestCase
{
    public function test_calls_the_client_resources_endpoint_with_bearer_auth(): void
    {
        $http = new FakeHttpRequester;
        $http->willReturn(200, '{"object":"stats","attributes":{"current_state":"running"}}');
        $connector = new PelicanConnector($http);

        $instance = new ConnectorInstance([
            'base_url' => 'https://panel.example.test',
            'credentials' => ['token' => 'ptlc_abc'],
        ]);

        $result = $connector->fetch($instance, ['server_identifier' => 'd3aac351']);

        $this->assertSame('running', $result['attributes']['current_state']);
        $this->assertSame(
            'https://panel.example.test/api/client/servers/d3aac351/resources',
            $http->lastUrl()
        );
        $this->assertSame('Bearer ptlc_abc', $http->lastHeaders()['Authorization']);
    }

    public function test_requires_a_server_identifier(): void
    {
        $connector = new PelicanConnector(new FakeHttpRequester);
        $instance = new ConnectorInstance(['base_url' => 'https://x', 'credentials' => ['token' => 't']]);

        $this->expectException(RuntimeException::class);
        $connector->fetch($instance, []);
    }

    public function test_requires_a_configured_token(): void
    {
        $connector = new PelicanConnector(new FakeHttpRequester);
        $instance = new ConnectorInstance(['base_url' => 'https://x', 'credentials' => []]);

        $this->expectException(RuntimeException::class);
        $connector->fetch($instance, ['server_identifier' => 'abc']);
    }

    public function test_lists_servers_the_api_key_can_see(): void
    {
        $http = new FakeHttpRequester;
        $http->willReturn(200, json_encode([
            'object' => 'list',
            'data' => [
                ['object' => 'server', 'attributes' => ['identifier' => 'd3aac351', 'name' => 'EU-1 Palworld']],
                ['object' => 'server', 'attributes' => ['identifier' => 'a1b2c3d4', 'name' => 'US-1 ARK']],
            ],
        ]));
        $connector = new PelicanConnector($http);

        $instance = new ConnectorInstance([
            'base_url' => 'https://panel.example.test',
            'credentials' => ['token' => 'ptlc_abc'],
        ]);

        $servers = $connector->listServers($instance);

        $this->assertSame([
            ['identifier' => 'd3aac351', 'name' => 'EU-1 Palworld'],
            ['identifier' => 'a1b2c3d4', 'name' => 'US-1 ARK'],
        ], $servers);
        $this->assertSame('https://panel.example.test/api/client', $http->lastUrl());
    }

    public function test_lists_no_servers_gracefully(): void
    {
        $http = new FakeHttpRequester;
        $http->willReturn(200, json_encode(['object' => 'list', 'data' => []]));
        $connector = new PelicanConnector($http);

        $instance = new ConnectorInstance(['base_url' => 'https://x', 'credentials' => ['token' => 't']]);

        $this->assertSame([], $connector->listServers($instance));
    }
}
