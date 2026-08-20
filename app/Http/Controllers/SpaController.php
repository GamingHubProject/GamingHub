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
    public function show(?string $path = null): Response
    {
        $root = resource_path('spa-dist');
        abort_unless(is_dir($root), 404);

        if ($path !== null) {
            $file = realpath($root.'/'.$path);
            if ($file !== false && str_starts_with($file, $root) && is_file($file)) {
                return response()->file($file);
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
