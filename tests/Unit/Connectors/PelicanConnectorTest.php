<?php

namespace Tests\Unit\Connectors;

use App\Connectors\PelicanConnector;
use App\Models\ConnectorInstance;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\Connectors\Support\FakeHttpRequester;

class PelicanConnectorTest extends TestCase
{
    public function test_fetch_calls_the_client_resources_endpoint_with_the_client_token(): void
    {
        $http = new FakeHttpRequester;
        $http->willReturn(200, '{"object":"stats","attributes":{"current_state":"running"}}');
        $connector = new PelicanConnector($http);

        $instance = new ConnectorInstance([
            'base_url' => 'https://panel.example.test',
            'credentials' => ['application_token' => 'ptla_admin', 'client_token' => 'ptlc_user'],
        ]);

        $result = $connector->fetch($instance, ['server_identifier' => 'd3aac351']);

        $this->assertSame('running', $result['attributes']['current_state']);
        $this->assertSame(
            'https://panel.example.test/api/client/servers/d3aac351/resources',
            $http->lastUrl()
        );
        $this->assertSame('Bearer ptlc_user', $http->lastHeaders()['Authorization']);
    }

    public function test_fetch_requires_a_server_identifier(): void
    {
        $connector = new PelicanConnector(new FakeHttpRequester);
        $instance = new ConnectorInstance(['base_url' => 'https://x', 'credentials' => ['client_token' => 't']]);

        $this->expectException(RuntimeException::class);
        $connector->fetch($instance, []);
    }

    public function test_fetch_requires_a_client_token_specifically(): void
    {
        $connector = new PelicanConnector(new FakeHttpRequester);
        // Only an application token configured — fetch() needs the client token.
        $instance = new ConnectorInstance([
            'base_url' => 'https://x',
            'credentials' => ['application_token' => 'ptla_admin'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Client API Key/');
        $connector->fetch($instance, ['server_identifier' => 'abc']);
    }

    public function test_lists_servers_via_the_application_api_with_the_application_token(): void
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
            'credentials' => ['application_token' => 'ptla_admin', 'client_token' => 'ptlc_user'],
        ]);

        $servers = $connector->listServers($instance);

        $this->assertSame([
            ['identifier' => 'd3aac351', 'name' => 'EU-1 Palworld'],
            ['identifier' => 'a1b2c3d4', 'name' => 'US-1 ARK'],
        ], $servers);
        $this->assertSame('https://panel.example.test/api/application/servers', $http->lastUrl());
        $this->assertSame('Bearer ptla_admin', $http->lastHeaders()['Authorization']);
    }

    public function test_list_servers_requires_an_application_token_specifically(): void
    {
        $connector = new PelicanConnector(new FakeHttpRequester);
        // Only a client token configured — listServers() needs the application token.
        $instance = new ConnectorInstance([
            'base_url' => 'https://x',
            'credentials' => ['client_token' => 'ptlc_user'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Application API Key/');
        $connector->listServers($instance);
    }

    public function test_lists_no_servers_gracefully(): void
    {
        $http = new FakeHttpRequester;
        $http->willReturn(200, json_encode(['object' => 'list', 'data' => []]));
        $connector = new PelicanConnector($http);

        $instance = new ConnectorInstance([
            'base_url' => 'https://x',
            'credentials' => ['application_token' => 'ptla_admin'],
        ]);

        $this->assertSame([], $connector->listServers($instance));
    }
}
