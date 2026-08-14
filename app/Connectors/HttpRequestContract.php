<?php

namespace App\Connectors;

/**
 * Lower-level than App\Manager\HttpClientContract (which is a plain
 * unauthenticated GET for downloading release zips) — Connectors need
 * custom headers, methods, and auth. Kept separate so each stays simple
 * for what it actually does.
 */
interface HttpRequestContract
{
    /**
     * @param  array<string, string>  $headers
     * @return array{status: int, body: string}
     */
    public function request(string $method, string $url, array $headers = []): array;
}
