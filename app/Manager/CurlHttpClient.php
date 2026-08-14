<?php

namespace App\Manager;

use RuntimeException;

final class CurlHttpClient implements HttpClientContract
{
    public function get(string $url): string
    {
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException("Refusing to fetch a non-HTTPS URL: [{$url}].");
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'GamingHub-Manager',
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("Failed to fetch [{$url}]: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Failed to fetch [{$url}]: HTTP {$status}");
        }

        return $body;
    }
}
