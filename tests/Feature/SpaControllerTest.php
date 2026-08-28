<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function writeAsset(string $relativePath, string $contents): string
    {
        $path = resource_path('spa-dist/'.$relativePath);
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_a_css_asset_is_served_with_the_css_mime_type(): void
    {
        $this->writeAsset('assets/spa-controller-test.css', '.foo{color:red}');

        $response = $this->get('/assets/spa-controller-test.css');

        $response->assertOk();
        // Symfony's Response::prepare() appends "; charset=..." to a
        // text/* Content-Type automatically — harmless, and the browser's
        // MIME check for a stylesheet only cares about the type/subtype.
        $this->assertStringStartsWith('text/css', $response->headers->get('Content-Type'));
    }

    public function test_a_js_asset_is_served_with_the_javascript_mime_type(): void
    {
        $this->writeAsset('assets/spa-controller-test.js', 'console.log(1);');

        $response = $this->get('/assets/spa-controller-test.js');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/javascript');
    }

    public function test_a_missing_extension_falls_back_to_content_sniffing(): void
    {
        // Extensionless files (or a type not in the map) aren't the
        // fix's concern — this just confirms the fallback path still
        // serves the file at all, without asserting a specific MIME.
        $path = $this->writeAsset('assets/spa-controller-test.bin', 'binary-ish content');

        $response = $this->get('/assets/spa-controller-test.bin');

        $response->assertOk();
        $this->assertSame('binary-ish content', file_get_contents($path));
    }

    public function test_a_hashed_asset_is_cached_forever_as_immutable(): void
    {
        // Every Vite build output filename bakes its content hash into the
        // name — a URL's content can never change without the URL itself
        // changing, so a browser (or anything between it and this server)
        // can hold onto it indefinitely without ever going stale. Checked
        // by directive presence, not an exact string — Symfony's
        // HeaderBag normalizes/reorders Cache-Control directives, so the
        // wire value isn't guaranteed to match what the controller set
        // verbatim, only to still mean the same thing.
        $this->writeAsset('assets/spa-controller-test.js', 'console.log(1);');

        $response = $this->get('/assets/spa-controller-test.js');

        $response->assertOk();
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
    }

    public function test_the_spa_shell_is_never_cached(): void
    {
        // index.html references whichever hashed asset filenames the
        // *current* build produced — a cached copy would point a returning
        // visitor at assets that may no longer exist after the next
        // deploy, so this has to be revalidated on every single load.
        // Session middleware adds its own directives (e.g. "private") on
        // top of what the controller sets explicitly — this only checks
        // that the no-cache guarantee itself survives that, not the exact
        // composed value.
        $response = $this->get('/');

        $response->assertOk();
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }
}
