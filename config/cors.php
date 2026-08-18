<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Covers the API routes and Sanctum's CSRF cookie endpoint — the two
    | surfaces the React SPA calls cross-origin. supports_credentials must
    | be true for the browser to send/receive the session cookie at all;
    | paired with SANCTUM_STATEFUL_DOMAINS (config/sanctum.php) which is
    | what actually authenticates the cookie once it arrives.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_merge(
        array_map(
            fn (string $domain) => 'https://'.$domain,
            array_filter(explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')))
        ),
        array_map(
            fn (string $domain) => 'http://'.$domain,
            array_filter(explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')))
        )
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
