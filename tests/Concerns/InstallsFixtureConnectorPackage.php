<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;

/**
 * Copies the fixture-panel connector+normalizer package (see
 * tests/Unit/Manager/fixtures/connectors/fixture-panel) into a real
 * storage/app/packages/{slug} directory for the duration of one test, and
 * removes it in tearDown() — the same install/cleanup shape
 * PackageLoaderTest already used for its own widget-status fixture, shared
 * here since multiple test classes now need a real, loadable connector +
 * package-owned normalizer that isn't the real (now external)
 * BasicConnectors package.
 */
trait InstallsFixtureConnectorPackage
{
    /** @var list<string> */
    protected array $fixturePackageSlugs = [];

    protected function tearDownInstallsFixtureConnectorPackage(): void
    {
        foreach ($this->fixturePackageSlugs as $slug) {
            File::deleteDirectory(storage_path("app/packages/{$slug}"));
        }
    }

    protected function installFixtureConnectorPackage(string $slug = 'fixture-panel'): void
    {
        File::copyDirectory(
            base_path('tests/Unit/Manager/fixtures/connectors/fixture-panel'),
            storage_path("app/packages/{$slug}"),
        );

        $this->fixturePackageSlugs[] = $slug;
    }
}
