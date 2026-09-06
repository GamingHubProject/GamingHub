<?php

namespace Tests\Feature;

use App\Experience\ThemeBundle;
use App\Experience\ThemeStorage;
use App\Manager\ExtensionDefinition;
use App\Manager\PackageInstaller;
use App\Models\InstalledPackage;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithThemes;
use Tests\TestCase;
use Tests\Unit\Manager\Support\FakeHttpClient;
use ZipArchive;

/**
 * The end-to-end path a theme takes from a registry to a themes folder:
 * registry JSON → release zip → checksum → ThemePackage → Theme row.
 */
class ThemeRegistryInstallTest extends TestCase
{
    use InteractsWithThemes;
    use RefreshDatabase;

    private const REGISTRY_URL = 'https://registry.test/extension_registry.json';

    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeThemeDisk();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            @unlink($f);
        }

        parent::tearDown();
    }

    private function registryJson(array $overrides = []): string
    {
        return json_encode([
            'schema' => 1,
            'id' => 'test-registry',
            'name' => 'Test Registry',
            'packages' => [array_merge([
                'id' => 'aurora-theme',
                'kind' => 'theme',
                'name' => 'Aurora',
                'description' => 'A theme package.',
                'author' => 'Tests',
                'category' => 'Themes',
                'repository' => 'https://github.com/Example/Themes',
                'release_asset' => 'aurora-*.zip',
                'checksum_asset' => 'SHA256SUMS',
            ], $overrides)],
        ]);
    }

    /** A theme package zip on disk, plus the http responses that serve it. */
    private function publish(FakeHttpClient $http, string $version, array $bundle = [], array $extraEntries = []): void
    {
        $path = sys_get_temp_dir().'/aurora_'.uniqid().'.zip';
        $this->tempFiles[] = $path;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('theme.json', json_encode(array_merge([
            'schema' => ThemeBundle::SCHEMA,
            'id' => 'aurora',
            'name' => 'Aurora',
            'version' => $version,
            'tokens' => ['accent' => '#33ddaa'],
        ], $bundle)));
        foreach ($extraEntries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        $asset = "aurora-{$version}.zip";
        $base = "https://github.com/Example/Themes/releases/download/v{$version}";
        $http->respond("{$base}/{$asset}", file_get_contents($path));
        $http->respond("{$base}/SHA256SUMS", hash_file('sha256', $path)."  {$asset}\n");
    }

    private function installer(FakeHttpClient $http): PackageInstaller
    {
        // Only the HTTP boundary is faked; everything below it is the real
        // downloader, verifier and ThemePackage.
        $this->app->instance(\App\Manager\HttpClientContract::class, $http);

        return $this->app->make(PackageInstaller::class);
    }

    public function test_installing_a_theme_package_creates_a_real_theme_folder(): void
    {
        $http = new FakeHttpClient;
        $http->respond(self::REGISTRY_URL, $this->registryJson());
        $this->publish($http, '1.0.0', extraEntries: ['favicon/icon.png' => 'PNGBYTES']);

        $result = $this->installer($http)->install(self::REGISTRY_URL, 'aurora-theme', '1.0.0');

        $this->assertSame('ok', $result['status'], $result['message']);

        $theme = Theme::where('name', 'Aurora')->first();
        $this->assertNotNull($theme);
        $this->assertSame('#33ddaa', $theme->bundle()->tokens['accent']);
        $this->assertTrue(
            Storage::disk(config('assets.disk'))->exists(app(ThemeStorage::class)->themePath($theme->slug, 'favicon/icon.png'))
        );
    }

    public function test_a_theme_is_not_installed_as_code(): void
    {
        $http = new FakeHttpClient;
        $http->respond(self::REGISTRY_URL, $this->registryJson());
        $this->publish($http, '1.0.0');

        $this->installer($http)->install(self::REGISTRY_URL, 'aurora-theme', '1.0.0');

        // storage/app/packages is for loadable code. A theme landing there
        // would be both useless and, since nothing would ever read it,
        // silently wrong.
        $this->assertDirectoryDoesNotExist(storage_path('app/packages/aurora-theme'));
    }

    public function test_it_records_the_install_so_the_registry_page_can_show_it(): void
    {
        $http = new FakeHttpClient;
        $http->respond(self::REGISTRY_URL, $this->registryJson());
        $this->publish($http, '1.2.0');

        $this->installer($http)->install(self::REGISTRY_URL, 'aurora-theme', '1.2.0');

        $record = InstalledPackage::where('slug', 'aurora-theme')->first();
        $this->assertNotNull($record);
        $this->assertSame(InstalledPackage::KIND_THEME, $record->kind);
        $this->assertSame(InstalledPackage::STATUS_INSTALLED, $record->status);
        $this->assertSame('1.2.0', $record->version);
        $this->assertSame(Theme::where('name', 'Aurora')->value('slug'), $record->manifest['theme_slug']);
    }

    public function test_reinstalling_a_newer_version_updates_the_theme_in_place(): void
    {
        $http = new FakeHttpClient;
        $http->respond(self::REGISTRY_URL, $this->registryJson());
        $this->publish($http, '1.0.0');
        $this->publish($http, '2.0.0', ['tokens' => ['accent' => '#ff8800']]);

        $installer = $this->installer($http);
        $installer->install(self::REGISTRY_URL, 'aurora-theme', '1.0.0');

        $theme = Theme::where('name', 'Aurora')->firstOrFail();
        ThemeAssignment::assign('platform', $theme->id);

        $result = $installer->install(self::REGISTRY_URL, 'aurora-theme', '2.0.0');

        $this->assertSame('ok', $result['status'], $result['message']);
        // One theme, not two: an update replaces rather than accumulates.
        $this->assertSame(1, Theme::where('name', 'Aurora')->count());
        $this->assertSame('#ff8800', $theme->refresh()->bundle()->tokens['accent']);
        // And the site it was themed for stays themed.
        $this->assertTrue(ThemeAssignment::where('theme_id', $theme->id)->exists());
        $this->assertSame('2.0.0', InstalledPackage::where('slug', 'aurora-theme')->value('version'));
    }

    public function test_an_update_follows_the_theme_even_after_it_is_renamed(): void
    {
        // The reason the install is recorded by slug rather than by name:
        // matching on name would install a second copy here and leave the
        // renamed original applied.
        $http = new FakeHttpClient;
        $http->respond(self::REGISTRY_URL, $this->registryJson());
        $this->publish($http, '1.0.0');
        $this->publish($http, '2.0.0', ['tokens' => ['accent' => '#ff8800']]);

        $installer = $this->installer($http);
        $installer->install(self::REGISTRY_URL, 'aurora-theme', '1.0.0');

        $theme = Theme::where('name', 'Aurora')->firstOrFail();
        $renamed = $theme->bundle();
        $renamed->name = 'My custom name';
        app(ThemeStorage::class)->writeBundle($theme, $renamed);

        $installer->install(self::REGISTRY_URL, 'aurora-theme', '2.0.0');

        // The update landed on the renamed theme rather than installing a
        // second copy beside it.
        $this->assertFalse(Theme::where('name', 'Aurora')->exists());
        $this->assertSame('#ff8800', $theme->refresh()->bundle()->tokens['accent']);
        // And it kept the admin's name: the package owns the contents, the
        // admin owns the label.
        $this->assertSame('My custom name', $theme->refresh()->bundle()->name);
    }

    public function test_a_theme_deleted_by_hand_reinstalls_as_a_fresh_one(): void
    {
        $http = new FakeHttpClient;
        $http->respond(self::REGISTRY_URL, $this->registryJson());
        $this->publish($http, '1.0.0');

        $installer = $this->installer($http);
        $installer->install(self::REGISTRY_URL, 'aurora-theme', '1.0.0');

        $theme = Theme::where('name', 'Aurora')->firstOrFail();
        app(ThemeStorage::class)->deleteTheme($theme);

        $result = $installer->install(self::REGISTRY_URL, 'aurora-theme', '1.0.0');

        $this->assertSame('ok', $result['status'], $result['message']);
        $this->assertSame(1, Theme::where('name', 'Aurora')->count());
    }

    public function test_a_tampered_release_is_refused(): void
    {
        $http = new FakeHttpClient;
        $http->respond(self::REGISTRY_URL, $this->registryJson());
        $this->publish($http, '1.0.0');
        // Same URL, different bytes than the checksum published for it.
        $http->respond(
            'https://github.com/Example/Themes/releases/download/v1.0.0/aurora-1.0.0.zip',
            'tampered contents'
        );

        $result = $this->installer($http)->install(self::REGISTRY_URL, 'aurora-theme', '1.0.0');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Checksum', $result['message']);
        $this->assertFalse(Theme::where('name', 'Aurora')->exists());
    }

    public function test_a_hostile_theme_package_from_a_registry_is_refused(): void
    {
        // Checksum verification proves the archive is the one the registry
        // published — not that what it published is safe to unpack.
        $http = new FakeHttpClient;
        $http->respond(self::REGISTRY_URL, $this->registryJson());
        $this->publish($http, '1.0.0', extraEntries: ['../../evil.php' => '<?php system($_GET["c"]);']);

        $result = $this->installer($http)->install(self::REGISTRY_URL, 'aurora-theme', '1.0.0');

        $this->assertSame('error', $result['status']);
        $this->assertFalse(Theme::where('name', 'Aurora')->exists());
        $this->assertFalse(InstalledPackage::where('slug', 'aurora-theme')->exists());
    }

    public function test_an_entry_with_no_kind_still_installs_as_an_extension(): void
    {
        // Every registry entry written before themes existed keeps meaning
        // what it meant.
        $definition = ExtensionDefinition::fromArray([
            'id' => 'legacy',
            'name' => 'Legacy',
            'repository' => 'https://github.com/Example/Legacy',
            'release_asset' => 'legacy-*.zip',
        ]);

        $this->assertSame(ExtensionDefinition::KIND_EXTENSION, $definition->kind);
        $this->assertFalse($definition->isTheme());
    }

    public function test_a_registry_entry_declaring_an_unknown_kind_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ExtensionDefinition::fromArray([
            'id' => 'weird',
            'name' => 'Weird',
            'repository' => 'https://github.com/Example/Weird',
            'release_asset' => 'weird-*.zip',
            'kind' => 'firmware',
        ]);
    }
}
