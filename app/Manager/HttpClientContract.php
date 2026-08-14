<?php

namespace App\Manager;

interface HttpClientContract
{
    /**
     * Fetch the raw bytes at $url, or throw if the request fails.
     */
    public function get(string $url): string;
}
