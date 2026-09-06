<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\BrowseRegistry;
use App\Manager\HttpClientContract;
use App\Models\InstalledPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Unit\Manager\Support\FakeHttpClient;

/**
 * Browse Registry needed no changes to list and install themes — the kind
 * branch lives in PackageInstaller, so the page keeps asking the same
 * question it always did. This holds that: if someone later moves the
 * branch up into the UI, these fail.
 */
class BrowseRegistryThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);

        $http = new FakeHttpClient;
        $http->respond(
            'https://raw.githubusercontent.com/GamingHubProject/Registry/main/extension_registry.json',
            json_encode([
                'schema' => 1,
                'id' => 'test',
                'name' => 'Test',
                'packages' => [[
                    'id' => 'aurora-theme',
                    'kind' => 'theme',
                    'name' => 'Aurora',
                    'description' => 'A theme.',
                    'category' => 'Themes',
                    'repository' => 'https://github.com/Example/Themes',
                    'release_asset' => 'aurora-*.zip',
                    'official' => true,
                ]],
            ])
        );

        $this->app->instance(HttpClientContract::class, $http);
    }

    public function test_a_theme_package_appears_in_the_registry_listing(): void
    {
        Livewire::test(BrowseRegistry::class)
            ->assertSuccessful()
            ->assertSet('packages.0.id', 'aurora-theme')
            ->assertSet('packages.0.category', 'Themes');
    }

    public function test_an_installed_theme_shows_its_version_like_any_other_package(): void
    {
        InstalledPackage::create([
            'slug' => 'aurora-theme',
            'kind' => InstalledPackage::KIND_THEME,
            'name' => 'Aurora',
            'version' => '1.1.0',
            'status' => InstalledPackage::STATUS_INSTALLED,
            'installed_at' => now(),
        ]);

        Livewire::test(BrowseRegistry::class)
            ->assertSet('packages.0.installedVersion', '1.1.0');
    }
}
