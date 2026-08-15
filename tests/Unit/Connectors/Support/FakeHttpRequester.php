<?php

namespace Tests\Unit\Connectors\Support;

use App\Connectors\HttpRequestContract;

final class FakeHttpRequester implements HttpRequestContract
{
    /** @var array{status: int, body: string}|null */
    private ?array $response = null;

    /** @var array<string, array{status: int, body: string}> keyed by a substring to match against the request URL */
    private array $responsesByUrl = [];

    private array $lastHeaders = [];

    private ?string $lastUrl = null;

    private ?string $lastMethod = null;

    public function willReturn(int $status, string $body): void
    {
        $this->response = ['status' => $status, 'body' => $body];
    }

    /** Respond differently depending on which URL is hit — for tests where more than one connector is called. */
    public function willReturnForUrl(string $urlContains, int $status, string $body): void
    {
        $this->responsesByUrl[$urlContains] = ['status' => $status, 'body' => $body];
    }

    public function request(string $method, string $url, array $headers = []): array
    {
        $this->lastMethod = $method;
        $this->lastUrl = $url;
        $this->lastHeaders = $headers;

        foreach ($this->responsesByUrl as $urlContains => $response) {
            if (str_contains($url, $urlContains)) {
                return $response;
            }
        }

        return $this->response ?? ['status' => 200, 'body' => '{}'];
    }

    public function lastHeaders(): array
    {
        return $this->lastHeaders;
    }

    public function lastUrl(): ?string
    {
        return $this->lastUrl;
    }

    public function lastMethod(): ?string
    {
        return $this->lastMethod;
    }
}
