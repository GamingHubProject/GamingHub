<?php

namespace Tests\Unit\Manager;

use App\Manager\PackageManifest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PackageManifestTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/ghm_manifest_test_'.uniqid();
        mkdir($this->workDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->workDir);
    }

    public function test_parses_a_valid_manifest(): void
    {
        $manifest = PackageManifest::fromJson(json_encode([
            'id' => 'maps',
            'name' => 'Maps',
            'version' => '0.1.000',
            'requires' => ['gaming-hub-core' => '>=0.1.010'],
        ]));

        $this->assertSame('maps', $manifest->id);
        $this->assertSame('0.1.000', $manifest->version);
        $this->assertSame(['gaming-hub-core' => '>=0.1.010'], $manifest->requires);
    }

    public function test_requires_defaults_to_empty(): void
    {
        $manifest = PackageManifest::fromJson(json_encode([
            'id' => 'maps',
            'name' => 'Maps',
            'version' => '0.1.000',
        ]));

        $this->assertSame([], $manifest->requires);
    }

    public function test_rejects_manifest_missing_required_field(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PackageManifest::fromJson(json_encode(['id' => 'maps', 'name' => 'Maps']));
    }

    public function test_reads_manifest_from_an_extracted_package_directory(): void
    {
        file_put_contents(
            $this->workDir.'/'.PackageManifest::FILENAME,
            json_encode(['id' => 'maps', 'name' => 'Maps', 'version' => '0.1.000'])
        );

        $manifest = PackageManifest::fromPackageDirectory($this->workDir);

        $this->assertNotNull($manifest);
        $this->assertSame('maps', $manifest->id);
    }

    public function test_returns_null_when_package_has_no_manifest(): void
    {
        $this->assertNull(PackageManifest::fromPackageDirectory($this->workDir));
    }
}
