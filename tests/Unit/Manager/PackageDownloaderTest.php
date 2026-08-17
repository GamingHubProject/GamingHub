<?php

namespace Tests\Unit\Manager;

use App\Manager\ExtensionDefinition;
use App\Manager\PackageDownloader;
use App\Manager\PackageManifest;
use Tests\Unit\Manager\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

class PackageDownloaderTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/ghm_test_'.uniqid();
        mkdir($this->workDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workDir);
    }

    public function test_resolves_asset_and_release_urls(): void
    {
        $downloader = new PackageDownloader(new FakeHttpClient);
        $extension = $this->coreExtension();

        $asset = $downloader->resolveAssetFilename($extension, '0.1.010');
        $this->assertSame('gaming-hub-core-v0.1.010.zip', $asset);

        $this->assertSame(
            'https://github.com/GamingHubProject/Core/releases/download/v0.1.010/gaming-hub-core-v0.1.010.zip',
            $downloader->releaseUrl($extension, '0.1.010', $asset)
        );
    }

    public function test_installs_a_verified_package_and_strips_the_top_level_directory(): void
    {
        $extension = $this->coreExtension();
        $version = '0.1.010';
        $assetFilename = 'gaming-hub-core-v0.1.010.zip';

        $zipPath = $this->buildFixtureZip($assetFilename, 'gaming-hub-core-0.1.010', [
            'composer.json' => '{"name":"gaming-hub-core"}',
            'src/Foo.php' => '<?php // foo',
        ]);
        $zipBytes = file_get_contents($zipPath);
        $hash = hash_file('sha256', $zipPath);

        $http = new FakeHttpClient;
        $http->respond(
            "https://github.com/GamingHubProject/Core/releases/download/v{$version}/{$assetFilename}",
            $zipBytes
        );
        $http->respond(
            "https://github.com/GamingHubProject/Core/releases/download/v{$version}/SHA256SUMS",
            "{$hash}  {$assetFilename}\n"
        );

        $destination = $this->workDir.'/installed';
        (new PackageDownloader($http))->install($extension, $version, $destination);

        $this->assertFileExists($destination.'/composer.json');
        $this->assertFileExists($destination.'/src/Foo.php');
        $this->assertDirectoryDoesNotExist($destination.'/gaming-hub-core-0.1.010');
    }

    public function test_installs_a_package_with_nested_subdirectories_intact(): void
    {
        // Regression test: moveContents() used to rely on a bare rename()
        // for every entry, which only has an automatic copy+unlink
        // fallback for plain files when source/destination are on
        // different filesystems — for a directory it just warned and left
        // it behind. Never caught before because nothing had ever
        // installed a real package with a subdirectory (a Connector's own
        // src/) through the real live path; this fixture goes two levels
        // deep specifically to prove the fix recurses, not just handles
        // one flat level.
        $extension = $this->coreExtension();
        $version = '0.1.010';
        $assetFilename = 'gaming-hub-core-v0.1.010.zip';

        $zipPath = $this->buildFixtureZip($assetFilename, 'gaming-hub-core-0.1.010', [
            'connector.json' => '{"class":"Foo"}',
            'src/Foo.php' => '<?php // foo',
            'src/Nested/Bar.php' => '<?php // bar',
            'src/Nested/Deeper/Baz.php' => '<?php // baz',
        ]);
        $zipBytes = file_get_contents($zipPath);
        $hash = hash_file('sha256', $zipPath);

        $http = new FakeHttpClient;
        $http->respond(
            "https://github.com/GamingHubProject/Core/releases/download/v{$version}/{$assetFilename}",
            $zipBytes
        );
        $http->respond(
            "https://github.com/GamingHubProject/Core/releases/download/v{$version}/SHA256SUMS",
            "{$hash}  {$assetFilename}\n"
        );

        $destination = $this->workDir.'/installed';
        (new PackageDownloader($http))->install($extension, $version, $destination);

        $this->assertFileExists($destination.'/src/Foo.php');
        $this->assertFileExists($destination.'/src/Nested/Bar.php');
        $this->assertFileExists($destination.'/src/Nested/Deeper/Baz.php');
        $this->assertSame('<?php // baz', file_get_contents($destination.'/src/Nested/Deeper/Baz.php'));
    }

    public function test_installed_packages_own_manifest_is_readable_afterward(): void
    {
        $extension = $this->coreExtension();
        $version = '0.1.010';
        $assetFilename = 'gaming-hub-core-v0.1.010.zip';

        $zipPath = $this->buildFixtureZip($assetFilename, 'gaming-hub-core-0.1.010', [
            'composer.json' => '{"name":"gaming-hub-core"}',
            PackageManifest::FILENAME => json_encode([
                'id' => 'gaming-hub-core',
                'name' => 'Gaming Hub Core',
                'version' => '0.1.010',
                'requires' => ['php' => '>=8.2'],
            ]),
        ]);
        $zipBytes = file_get_contents($zipPath);
        $hash = hash_file('sha256', $zipPath);

        $http = new FakeHttpClient;
        $http->respond(
            "https://github.com/GamingHubProject/Core/releases/download/v{$version}/{$assetFilename}",
            $zipBytes
        );
        $http->respond(
            "https://github.com/GamingHubProject/Core/releases/download/v{$version}/SHA256SUMS",
            "{$hash}  {$assetFilename}\n"
        );

        $destination = $this->workDir.'/installed';
        (new PackageDownloader($http))->install($extension, $version, $destination);

        $manifest = PackageManifest::fromPackageDirectory($destination);

        $this->assertNotNull($manifest);
        $this->assertSame('0.1.010', $manifest->version);
        $this->assertSame(['php' => '>=8.2'], $manifest->requires);
    }

    public function test_refuses_to_install_when_checksum_does_not_match(): void
    {
        $extension = $this->coreExtension();
        $version = '0.1.010';
        $assetFilename = 'gaming-hub-core-v0.1.010.zip';

        $zipPath = $this->buildFixtureZip($assetFilename, 'gaming-hub-core-0.1.010', [
            'composer.json' => '{"name":"gaming-hub-core"}',
        ]);
        $zipBytes = file_get_contents($zipPath);

        $http = new FakeHttpClient;
        $http->respond(
            "https://github.com/GamingHubProject/Core/releases/download/v{$version}/{$assetFilename}",
            $zipBytes
        );
        $http->respond(
            "https://github.com/GamingHubProject/Core/releases/download/v{$version}/SHA256SUMS",
            str_repeat('a', 64)."  {$assetFilename}\n"
        );

        $destination = $this->workDir.'/installed';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Checksum verification failed/');

        (new PackageDownloader($http))->install($extension, $version, $destination);
    }

    private function coreExtension(): ExtensionDefinition
    {
        return ExtensionDefinition::fromArray([
            'id' => 'gaming-hub-core',
            'name' => 'Gaming Hub Core',
            'repository' => 'https://github.com/GamingHubProject/Core',
            'release_asset' => 'gaming-hub-core-v*.zip',
        ]);
    }

    /**
     * @param  array<string, string>  $files  relative path (inside the wrapping dir) => contents
     */
    private function buildFixtureZip(string $zipFilename, string $wrappingDir, array $files): string
    {
        $zipPath = $this->workDir.'/'.$zipFilename;
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach ($files as $relativePath => $contents) {
            $zip->addFromString("{$wrappingDir}/{$relativePath}", $contents);
        }

        $zip->close();

        return $zipPath;
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
