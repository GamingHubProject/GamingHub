<?php

namespace Tests\Feature;

use App\Models\SiteOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteOptionApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_always_returns_the_same_singleton_row(): void
    {
        // AppServiceProvider::boot() already calls current() once during
        // this test's own app bootstrap (to apply site_name/timezone), so
        // the row exists before this test body runs — what matters is
        // that repeated calls never create a second row.
        $first = SiteOption::current();
        $second = SiteOption::current();

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('site_options', 1);
    }

    public function test_value_reads_a_single_key_with_a_default_fallback(): void
    {
        SiteOption::current()->update(['values' => ['site_name' => 'Test Hub']]);

        $this->assertSame('Test Hub', SiteOption::value('site_name'));
        $this->assertSame('fallback', SiteOption::value('missing_key', 'fallback'));
    }

    public function test_site_name_becomes_the_apps_config_name_on_boot(): void
    {
        SiteOption::current()->update(['values' => ['site_name' => 'Booted Hub Name']]);

        // Re-run boot logic the way a fresh request would — AppServiceProvider
        // already ran once during this test's app bootstrap before the
        // update above, so call it again explicitly.
        $this->app->register(\App\Providers\AppServiceProvider::class, force: true);

        $this->assertSame('Booted Hub Name', config('app.name'));
    }

    public function test_the_welcome_page_title_reflects_site_name(): void
    {
        SiteOption::current()->update(['values' => ['site_name' => 'Booted Hub Name']]);
        $this->app->register(\App\Providers\AppServiceProvider::class, force: true);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title>Booted Hub Name</title>', false);
    }

    public function test_timezone_becomes_the_apps_config_and_php_default_timezone_on_boot(): void
    {
        SiteOption::current()->update(['values' => ['timezone' => 'Europe/Berlin']]);

        $this->app->register(\App\Providers\AppServiceProvider::class, force: true);

        $this->assertSame('Europe/Berlin', config('app.timezone'));
        $this->assertSame('Europe/Berlin', date_default_timezone_get());
    }
}
