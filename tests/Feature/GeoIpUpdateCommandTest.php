<?php

namespace Tests\Feature;

use App\Services\GeoIpLookup;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The successful download-validate-swap path needs a real, valid .mmdb
 * byte sequence to fake a response body with — maxmind-db/reader doesn't
 * ship test fixtures via Composer, and hand-constructing a valid MMDB
 * binary isn't practical here. That path is verified by actually running
 * the command against the real DB-IP endpoint instead of a unit test.
 * These tests cover what's fully deterministic without either: every
 * failure mode leaves the existing file (if any) untouched.
 */
class GeoIpUpdateCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete((new GeoIpLookup)->path());

        parent::tearDown();
    }

    public function test_both_months_returning_404_fails_without_creating_a_file(): void
    {
        Http::fake(['download.db-ip.com/*' => Http::response('Not Found', 404)]);

        $this->artisan('gaming-hub:geoip-update')->assertExitCode(1);

        $this->assertFileDoesNotExist((new GeoIpLookup)->path());
    }

    public function test_a_network_exception_is_handled_without_crashing(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Could not resolve host');
        });

        $this->artisan('gaming-hub:geoip-update')->assertExitCode(1);
    }

    public function test_a_non_gzip_response_body_fails_validation(): void
    {
        Http::fake(['download.db-ip.com/*' => Http::response('this is not gzip data', 200)]);

        $this->artisan('gaming-hub:geoip-update')->assertExitCode(1);

        $this->assertFileDoesNotExist((new GeoIpLookup)->path());
    }

    public function test_a_failed_update_leaves_an_existing_database_file_untouched(): void
    {
        $lookup = new GeoIpLookup;
        File::ensureDirectoryExists(dirname($lookup->path()));
        File::put($lookup->path(), 'existing-database-contents');

        Http::fake(['download.db-ip.com/*' => Http::response('Not Found', 404)]);

        $this->artisan('gaming-hub:geoip-update')->assertExitCode(1);

        $this->assertSame('existing-database-contents', File::get($lookup->path()));
    }

    public function test_it_tries_the_current_month_before_falling_back_to_the_previous_one(): void
    {
        $requestedUrls = [];
        Http::fake(function ($request) use (&$requestedUrls) {
            $requestedUrls[] = $request->url();

            return Http::response('Not Found', 404);
        });

        $this->artisan('gaming-hub:geoip-update')->assertExitCode(1);

        $currentMonth = now()->format('Y-m');
        $previousMonth = now()->subMonth()->format('Y-m');

        $this->assertStringContainsString($currentMonth, $requestedUrls[0]);
        $this->assertStringContainsString($previousMonth, $requestedUrls[1]);
    }
}
