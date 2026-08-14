<?php

namespace Tests\Unit\Connectors;

use App\Connectors\RestConnector;
use App\Models\ConnectorInstance;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\Connectors\Support\FakeHttpRequester;

class RestConnectorTest extends TestCase
{
    public function test_uses_basic_auth_when_username_and_password_are_set(): void
    {
        $http = new FakeHttpRequester;
        $http->willReturn(200, '{"currentplayernum":3}');
        $connector = new RestConnector($http);

        $instance = new ConnectorInstance([
            'base_url' => 'http://palworld-server:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);

        $result = $connector->fetch($instance, ['endpoint' => '/v1/api/metrics']);

        $this->assertSame(['currentplayernum' => 3], $result);
        $this->assertSame('http://palworld-server:8212/v1/api/metrics', $http->lastUrl());
        $this->assertSame('Basic '.base64_encode('admin:secret'), $http->lastHeaders()['Authorization']);
    }

    public function test_uses_bearer_auth_when_token_is_set(): void
    {
        $http = new FakeHttpRequester;
        $http->willReturn(200, '{}');
        $connector = new RestConnector($http);

        $instance = new ConnectorInstance([
            'base_url' => 'https://api.example.test',
            'credentials' => ['token' => 'abc123'],
        ]);

        $connector->fetch($instance, ['endpoint' => '/status']);

        $this->assertSame('Bearer abc123', $http->lastHeaders()['Authorization']);
    }

    public function test_throws_on_non_2xx_response(): void
    {
        $http = new FakeHttpRequester;
        $http->willReturn(401, 'Unauthorized');
        $connector = new RestConnector($http);

        $instance = new ConnectorInstance(['base_url' => 'http://x', 'credentials' => []]);

        $this->expectException(RuntimeException::class);
        $connector->fetch($instance, ['endpoint' => '/x']);
    }

    public function test_throws_on_invalid_json(): void
    {
        $http = new FakeHttpRequester;
        $http->willReturn(200, 'not json');
        $connector = new RestConnector($http);

        $instance = new ConnectorInstance(['base_url' => 'http://x', 'credentials' => []]);

        $this->expectException(RuntimeException::class);
        $connector->fetch($instance, ['endpoint' => '/x']);
    }
}
