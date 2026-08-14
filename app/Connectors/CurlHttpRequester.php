<?php

namespace App\Connectors;

use RuntimeException;

final class CurlHttpRequester implements HttpRequestContract
{
    public function request(string $method, string $url, array $headers = []): array
    {
        $ch = curl_init($url);

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'GamingHub-Panel',
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("Request to [{$url}] failed: {$error}");
        }

        return ['status' => $status, 'body' => $body];
    }
}
