<?php

namespace Tests\Unit\Manager\Support;

use App\Manager\HttpClientContract;
use RuntimeException;

final class FakeHttpClient implements HttpClientContract
{
    /** @var array<string, string> */
    private array $responses = [];

    public function respond(string $url, string $body): void
    {
        $this->responses[$url] = $body;
    }

    public function get(string $url): string
    {
        return $this->responses[$url]
            ?? throw new RuntimeException("FakeHttpClient has no response configured for [{$url}].");
    }
}
