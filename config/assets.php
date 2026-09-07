<?php

return [

    // One disk for the whole library — switching to S3/a CDN later is this
    // one value (plus filling in the AWS_* credentials already stubbed in
    // .env.example), not a code change. See config/filesystems.php.
    'disk' => env('ASSETS_DISK', 'public'),

    'max_size_kb' => env('ASSETS_MAX_SIZE_KB', 5120), // 5MB

    // woff/woff2 for the Theme font system (see App\Models\Asset::isNonRasterMime
    // and AssetController::dimensions) — deliberately not TTF/OTF: those
    // aren't web-safe formats, and every browser this app targets accepts
    // woff2 (with woff as the one fallback worth bothering with).
    'allowed_mimes' => ['png', 'jpg', 'jpeg', 'webp', 'svg', 'woff', 'woff2'],

    // What a non-admin may upload as their own avatar — the one upload
    // path in this app that isn't admin-only (AssetController::store).
    // Raster only: an SVG is an image the browser executes script from,
    // and these files are served from the app's own origin. Smaller cap
    // too, since the endpoint is now reachable by every signed-in visitor
    // and an avatar has no business being five megabytes.
    'avatar_mimes' => ['png', 'jpg', 'jpeg', 'webp'],

    'avatar_max_size_kb' => env('ASSETS_AVATAR_MAX_SIZE_KB', 1024), // 1MB

    // Cheap decompression-bomb guard, not a real limit on legitimate
    // assets — well beyond anything a banner/icon/background needs.
    // Doesn't apply to SVG, which has no fixed raster size.
    'max_dimension_px' => 8000,

    'thumbnail' => [
        'max_width' => 320,
        'max_height' => 320,
    ],

];
