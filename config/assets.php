<?php

return [

    // One disk for the whole library — switching to S3/a CDN later is this
    // one value (plus filling in the AWS_* credentials already stubbed in
    // .env.example), not a code change. See config/filesystems.php.
    'disk' => env('ASSETS_DISK', 'public'),

    'max_size_kb' => env('ASSETS_MAX_SIZE_KB', 5120), // 5MB

    'allowed_mimes' => ['png', 'jpg', 'jpeg', 'webp', 'svg'],

    // Cheap decompression-bomb guard, not a real limit on legitimate
    // assets — well beyond anything a banner/icon/background needs.
    // Doesn't apply to SVG, which has no fixed raster size.
    'max_dimension_px' => 8000,

    'thumbnail' => [
        'max_width' => 320,
        'max_height' => 320,
    ],

];
