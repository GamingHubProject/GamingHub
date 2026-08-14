<?php

namespace Tests\Unit\Connectors\Support;

use App\Connectors\HttpRequestContract;

final class FakeHttpRequester implements HttpRequestContract
{
    /** @var array{status: int, body: string}|null */
    private ?array $response = null;

    private array $lastHeaders = [];

    private ?string $lastUrl = null;

    private ?string $lastMethod = null;

    public function willReturn(int $status, string $body): void
    {
        $this->response = ['status' => $status, 'body' => $body];
    }

    public function request(string $method, string $url, array $headers = []): array
    {
        $this->lastMethod = $method;
        $this->lastUrl = $url;
        $this->lastHeaders = $headers;

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
