<?php

namespace App\Http\Controllers;

use App\Experience\ThemeResolver;
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
                // Every asset under /assets/ is Vite's content-hashed output
                // (e.g. index-Bu05bJdl.js) — the hash changes if and only if
                // the content does, so a URL never changes meaning and can
                // safely be cached forever. Explicit, not left to
                // response()->file()'s own default ('public' with no
                // max-age, i.e. heuristic freshness only) — a browser or
                // intermediary holding onto a stale JS chunk past whatever
                // it guessed was reasonable is exactly the kind of "the
                // deploy looks right but the browser didn't notice" gap
                // this is meant to close off entirely.
                $headers['Cache-Control'] = 'public, max-age=31536000, immutable';

                return response()->file($file, $headers);
            }
        }

        // The build bakes in a static <title>; every page is this same
        // index.html (SPA convention), so rewrite it here to keep reflecting
        // the admin-configurable site name the old welcome.blade.php showed
        // (see SiteOptionApplicationTest and AppServiceProvider::boot()).
        $html = file_get_contents($root.'/index.html');
        $html = preg_replace('/<title>.*?<\/title>/s', '<title>'.e(config('app.name')).'</title>', $html, 1);
        $html = $this->injectFavicon($html);

        // The opposite of the asset branch above: this response's meaning
        // changes on every deploy (it references whichever hashed asset
        // filenames are current), so it must always be revalidated, never
        // served stale. Set explicitly rather than assumed from session
        // middleware's own no-cache side effect, which only fires once a
        // session has actually started.
        return response($html)->header('Content-Type', 'text/html')->header('Cache-Control', 'no-cache, must-revalidate');
    }

    /**
     * The admin-chosen favicon has to be in the served HTML, not applied
     * by the SPA once it boots: a browser requests /favicon.ico (or
     * whatever <link rel="icon"> says) while parsing <head>, long before
     * any JS runs, so a React-side effect would at best cause a visible
     * swap and at worst be ignored entirely. Same reasoning as the <title>
     * rewrite directly above — this shell is already re-rendered per
     * request and served no-cache, so reading a DB value here costs one
     * query on a response that was never cacheable anyway.
     *
     * Injected before </head> rather than replacing an existing tag: the
     * built index.html ships no icon link at all, and adding a placeholder
     * to it just to have something to substitute would put a second source
     * of truth in the SPA's own template. No favicon configured means no
     * tag, which leaves the browser's default /favicon.ico probe intact
     * exactly as today.
     */
    private function injectFavicon(string $html): string
    {
        // The platform theme's, not a scoped one: this shell is served
        // before any route has matched, so there's no game or server yet
        // to narrow the theme with.
        $url = app(ThemeResolver::class)->platformFavicon();
        if (! $url) {
            return $html;
        }

        $tag = '<link rel="icon" href="'.e($url).'">';

        return str_replace('</head>', $tag.'</head>', $html);
    }
}
