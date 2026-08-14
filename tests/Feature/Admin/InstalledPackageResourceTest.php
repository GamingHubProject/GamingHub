<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\InstalledPackageResource\Pages\CreateInstalledPackage;
use App\Filament\Resources\InstalledPackageResource\Pages\ListInstalledPackages;
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

class InstalledPackageResourceTest extends TestCase
{
    use RefreshDatabase;

    private array $tempFiles = [];

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

    public function test_can_list_packages(): void
    {
        InstalledPackage::factory()->count(2)->create();

        Livewire::test(ListInstalledPackages::class)->assertSuccessful();
    }

    public function test_can_register_a_package_unbound_to_a_game(): void
    {
        Livewire::test(CreateInstalledPackage::class)
            ->fillForm([
                'slug' => 'maps',
                'name' => 'Maps',
                'version' => '0.1.000',
                'status' => 'disabled',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('installed_packages', [
            'slug' => 'maps',
            'game_id' => null,
        ]);
    }

    public function test_can_toggle_status(): void
    {
        $package = InstalledPackage::factory()->create(['status' => 'disabled']);

        $package->update(['status' => 'enabled']);

        $this->assertSame('enabled', $package->fresh()->status);
    }

    public function test_install_from_registry_downloads_verifies_and_records_the_package(): void
    {
        $registryUrl = 'https://example.test/extension_registry.json';
        $zipUrl = 'https://github.com/GamingHubProject/Hub-Extensions/releases/download/v0.1.000/maps-v0.1.000.zip';
        $checksumUrl = 'https://github.com/GamingHubProject/Hub-Extensions/releases/download/v0.1.000/SHA256SUMS';

        $registryJson = json_encode([
            'schema' => 1,
            'id' => 'test',
            'name' => 'Test',
            'packages' => [[
                'id' => 'maps',
                'name' => 'Maps',
                'repository' => 'https://github.com/GamingHubProject/Hub-Extensions',
                'release_asset' => 'maps-v*.zip',
            ]],
        ]);

        $zipPath = $this->buildFixtureZip('maps-v0.1.000.zip', 'maps-0.1.000', [
            PackageManifest::FILENAME => json_encode([
                'id' => 'maps', 'name' => 'Maps', 'version' => '0.1.000',
            ]),
        ]);
        $zipBytes = file_get_contents($zipPath);
        $hash = hash_file('sha256', $zipPath);

        $fake = new FakeHttpClient;
        $fake->respond($registryUrl, $registryJson);
        $fake->respond($zipUrl, $zipBytes);
        $fake->respond($checksumUrl, "{$hash}  maps-v0.1.000.zip\n");
        $this->app->instance(HttpClientContract::class, $fake);

        Livewire::test(ListInstalledPackages::class)
            ->callAction('installFromRegistry', [
                'registry_url' => $registryUrl,
                'package_id' => 'maps',
                'version' => '0.1.000',
            ]);

        $this->assertDatabaseHas('installed_packages', [
            'slug' => 'maps',
            'version' => '0.1.000',
            'status' => 'disabled',
        ]);
    }

    public function test_install_from_registry_reports_a_missing_package(): void
    {
        $registryUrl = 'https://example.test/extension_registry.json';

        $fake = new FakeHttpClient;
        $fake->respond($registryUrl, json_encode([
            'schema' => 1, 'id' => 'test', 'name' => 'Test', 'packages' => [],
        ]));
        $this->app->instance(HttpClientContract::class, $fake);

        Livewire::test(ListInstalledPackages::class)
            ->callAction('installFromRegistry', [
                'registry_url' => $registryUrl,
                'package_id' => 'does-not-exist',
                'version' => '0.1.000',
            ]);

        $this->assertDatabaseMissing('installed_packages', ['slug' => 'does-not-exist']);
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
