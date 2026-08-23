<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the built React SPA from resources/spa-dist (production build only
 * — that directory doesn't exist in dev, where the SPA runs from its own
 * Vite dev server instead). Serves everything explicitly rather than
 * leaning on the webserver's own static-file detection: a real asset path
 * (e.g. /assets/index-abc123.js) returns that file directly; anything else
 * (a client-side route, or a bare visit) returns the SPA's index.html and
 * lets its React Router take over. See routes/web.php for why this needs
 * its own named /dashboard route in addition to the general catch-all.
 */
class SpaController extends Controller
{
    /**
     * response()->file() otherwise leaves Content-Type to PHP's fileinfo
     * extension sniffing the file's bytes — fine for a binary format like a
     * font or image, but a plain-text asset like CSS has no distinguishing
     * signature and gets misdetected as text/plain. Browsers silently
     * refuse to apply a <link rel="stylesheet"> whose response isn't a CSS
     * MIME type (no error, no console warning — the sheet just loads with
     * zero parsed rules), so this was quietly breaking every external
     * stylesheet the SPA ships without ever throwing anything visibly
     * wrong. Extension-based, not content-sniffed — a build asset's
     * meaning comes from what it *is* (a .css file), not a guess from its
     * bytes.
     *
     * @var array<string, string>
     */
    private const MIME_TYPES = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'application/javascript',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    public function show(?string $path = null): Response
    {
        $root = resource_path('spa-dist');
        abort_unless(is_dir($root), 404);

        if ($path !== null) {
            $file = realpath($root.'/'.$path);
            if ($file !== false && str_starts_with($file, $root) && is_file($file)) {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $headers = isset(self::MIME_TYPES[$extension]) ? ['Content-Type' => self::MIME_TYPES[$extension]] : [];

                return response()->file($file, $headers);
            }
        }

        // The build bakes in a static <title>; every page is this same
        // index.html (SPA convention), so rewrite it here to keep reflecting
        // the admin-configurable site name the old welcome.blade.php showed
        // (see SiteOptionApplicationTest and AppServiceProvider::boot()).
        $html = file_get_contents($root.'/index.html');
        $html = preg_replace('/<title>.*?<\/title>/s', '<title>'.e(config('app.name')).'</title>', $html, 1);

        return response($html)->header('Content-Type', 'text/html');
    }
}
