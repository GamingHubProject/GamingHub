<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\BrowseRegistry;
use App\Manager\HttpClientContract;
use App\Manager\PackageManifest;
use App\Models\InstalledPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Unit\Manager\Support\FakeHttpClient;
use ZipArchive;

class BrowseRegistryTest extends TestCase
{
    use RefreshDatabase;

    private array $tempFiles = [];

    private string $registryUrl = 'https://raw.githubusercontent.com/GamingHubProject/Registry/main/extension_registry.json';

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_lists_packages_from_a_live_registry_not_a_blind_text_field(): void
    {
        $fake = new FakeHttpClient;
        $fake->respond($this->registryUrl, json_encode([
            'schema' => 1,
            'id' => 'test',
            'name' => 'Test',
            'packages' => [[
                'id' => 'maps',
                'name' => 'Maps',
                'description' => 'Interactive server maps',
                'category' => 'Hub Extensions',
                'repository' => 'https://github.com/GamingHubProject/Hub-Extensions',
                'release_asset' => 'maps-v*.zip',
                'official' => true,
            ]],
        ]));
        $this->app->instance(HttpClientContract::class, $fake);

        Livewire::test(BrowseRegistry::class)
            ->assertSuccessful()
            ->assertSet('packages.0.id', 'maps')
            ->assertSet('packages.0.name', 'Maps')
            ->assertSet('packages.0.installedVersion', null);
    }

    public function test_shows_already_installed_version(): void
    {
        InstalledPackage::factory()->create(['slug' => 'maps', 'version' => '0.2.000']);

        $fake = new FakeHttpClient;
        $fake->respond($this->registryUrl, json_encode([
            'schema' => 1, 'id' => 'test', 'name' => 'Test',
            'packages' => [[
                'id' => 'maps', 'name' => 'Maps',
                'repository' => 'https://github.com/GamingHubProject/Hub-Extensions',
                'release_asset' => 'maps-v*.zip',
            ]],
        ]));
        $this->app->instance(HttpClientContract::class, $fake);

        Livewire::test(BrowseRegistry::class)
            ->assertSet('packages.0.installedVersion', '0.2.000');
    }

    public function test_reports_a_registry_it_cannot_reach(): void
    {
        $fake = new FakeHttpClient; // no response configured -> throws
        $this->app->instance(HttpClientContract::class, $fake);

        Livewire::test(BrowseRegistry::class)
            ->assertSuccessful()
            ->assertSet('packages', []);
    }

    public function test_clicking_refresh_always_shows_a_notification(): void
    {
        $fake = new FakeHttpClient;
        $fake->respond($this->registryUrl, json_encode([
            'schema' => 1, 'id' => 'test', 'name' => 'Test', 'packages' => [],
        ]));
        $this->app->instance(HttpClientContract::class, $fake);

        Livewire::test(BrowseRegistry::class)
            ->call('refresh')
            ->assertNotified();
    }

    public function test_clicking_refresh_notifies_on_failure_too(): void
    {
        $fake = new FakeHttpClient; // no response configured -> throws
        $this->app->instance(HttpClientContract::class, $fake);

        Livewire::test(BrowseRegistry::class)
            ->call('refresh')
            ->assertNotified();
    }

    public function test_clicking_install_downloads_verifies_and_records_the_package(): void
    {
        $zipUrl = 'https://github.com/GamingHubProject/Hub-Extensions/releases/download/v0.1.000/maps-v0.1.000.zip';
        $checksumUrl = 'https://github.com/GamingHubProject/Hub-Extensions/releases/download/v0.1.000/SHA256SUMS';

        $fake = new FakeHttpClient;
        $fake->respond($this->registryUrl, json_encode([
            'schema' => 1, 'id' => 'test', 'name' => 'Test',
            'packages' => [[
                'id' => 'maps', 'name' => 'Maps',
                'repository' => 'https://github.com/GamingHubProject/Hub-Extensions',
                'release_asset' => 'maps-v*.zip',
            ]],
        ]));

        $zipPath = $this->buildFixtureZip('maps-v0.1.000.zip', 'maps-0.1.000', [
            PackageManifest::FILENAME => json_encode(['id' => 'maps', 'name' => 'Maps', 'version' => '0.1.000']),
        ]);
        $hash = hash_file('sha256', $zipPath);
        $fake->respond($zipUrl, file_get_contents($zipPath));
        $fake->respond($checksumUrl, "{$hash}  maps-v0.1.000.zip\n");
        $this->app->instance(HttpClientContract::class, $fake);

        Livewire::test(BrowseRegistry::class)
            ->mountAction('install', ['packageId' => 'maps'])
            ->setActionData(['version' => '0.1.000'])
            ->callMountedAction()
            ->assertSet('packages.0.installedVersion', '0.1.000');

        $this->assertDatabaseHas('installed_packages', [
            'slug' => 'maps',
            'version' => '0.1.000',
            'status' => 'disabled',
        ]);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function buildFixtureZip(string $zipFilename, string $wrappingDir, array $files): string
    {
        $zipPath = sys_get_temp_dir().'/'.uniqid('ghtest_').'_'.$zipFilename;
        $this->tempFiles[] = $zipPath;

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach ($files as $relativePath => $contents) {
            $zip->addFromString("{$wrappingDir}/{$relativePath}", $contents);
        }

        $zip->close();

        return $zipPath;
    }
}
