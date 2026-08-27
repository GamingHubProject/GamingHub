<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Covers the API routes, Sanctum's CSRF cookie endpoint, and public
    | storage — the surfaces the React SPA touches cross-origin.
    | supports_credentials must be true for the browser to send/receive
    | the session cookie at all; paired with SANCTUM_STATEFUL_DOMAINS
    | (config/sanctum.php) which is what actually authenticates the
    | cookie once it arrives.
    |
    | storage/* is needed even though it's unauthenticated: an <img> tag
    | doesn't enforce CORS, but the Font Loading API's FontFace() does —
    | it's a programmatic fetch of the asset bytes, not a passive embed
    | (see ThemeProvider's @font-face injection). Without this, a page's
    | font silently fails to load whenever the SPA's origin differs from
    | the storage disk's (e.g. the vite dev server on a different port).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

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
