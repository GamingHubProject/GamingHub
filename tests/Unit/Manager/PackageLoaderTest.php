<?php

namespace Tests\Unit\Manager;

use App\Connectors\ConnectorRegistry;
use App\Manager\PackageLoader;
use App\Models\InstalledPackage;
use GamingHubFixtures\WidgetStatus\WidgetStatusConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Proves the previously-missing half of Manager: an enabled InstalledPackage
 * with a connector.json actually gets its PHP code loaded and registered
 * into ConnectorRegistry — not just downloaded and recorded in the
 * database. Two fixtures cover two real cases:
 *
 * - widget-status: a class that lives NOWHERE Composer's own autoloader
 *   reaches, proving PackageLoader's spl_autoload_register path actually
 *   pulls in code from an installed package directory. Copied into a
 *   throwaway slug under storage/app/packages/ and cleaned up by exact
 *   path afterward — never the whole packages/ directory, which also
 *   holds the real bundled pelican-connector package below (wiping it
 *   would break every other test/request that resolves 'pelican' for the
 *   rest of the process).
 * - pelican-connector: NOT copied in by this test — storage/app/packages/
 *   pelican-connector/connector.json is a real, git-tracked, bundled
 *   package (see PackageLoader's own docblock), the actual replacement
 *   for AppServiceProvider's old hardcoded
 *   $connectors->register(PelicanConnector::class) line. This test proves
 *   the shipped file is what makes 'pelican' resolvable now, not a
 *   test-local copy.
 */
class PackageLoaderTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    protected array $installedFixtureSlugs = [];

    protected function tearDown(): void
    {
        foreach ($this->installedFixtureSlugs as $slug) {
            File::deleteDirectory(storage_path("app/packages/{$slug}"));
        }

        parent::tearDown();
    }

    public function test_loads_and_registers_a_connector_whose_class_is_not_already_autoloaded(): void
    {
        $this->installFixture('widget-status', 'widget-status-fixture');
        InstalledPackage::factory()->create(['slug' => 'widget-status-fixture', 'status' => 'enabled']);

        app(PackageLoader::class)->loadConnectorPackages();

        $connector = app(ConnectorRegistry::class)->get('widget-status-fixture');

        $this->assertInstanceOf(WidgetStatusConnector::class, $connector);
        $this->assertSame(['online' => true, 'source' => 'fixture'], $connector->fetch(new \App\Models\ConnectorInstance, []));
    }

    public function test_the_real_pelican_connector_is_registered_via_the_loader_not_a_hardcoded_boot_call(): void
    {
        $this->assertFileExists(
            storage_path('app/packages/pelican-connector/connector.json'),
            'The bundled Pelican Connector package is missing — it ships in the repo (storage/app/packages/pelican-connector/), it should never need installing.'
        );

        InstalledPackage::factory()->create(['slug' => 'pelican-connector', 'status' => 'enabled']);

        app(PackageLoader::class)->loadConnectorPackages();

        $connector = app(ConnectorRegistry::class)->get('pelican');

        $this->assertInstanceOf(\App\Connectors\PelicanConnector::class, $connector);
    }

    public function test_installed_packages_without_a_connector_json_are_skipped_without_error(): void
    {
        InstalledPackage::factory()->create(['slug' => 'some-hub-extension', 'status' => 'enabled']);

        app(PackageLoader::class)->loadConnectorPackages();

        $this->expectException(InvalidArgumentException::class);
        app(ConnectorRegistry::class)->get('some-hub-extension');
    }

    public function test_a_disabled_connector_package_is_never_loaded(): void
    {
        $this->installFixture('widget-status', 'widget-status-fixture-disabled');
        InstalledPackage::factory()->create(['slug' => 'widget-status-fixture-disabled', 'status' => 'disabled']);

        app(PackageLoader::class)->loadConnectorPackages();

        // The fixture's own type() is always 'widget-status-fixture'
        // regardless of the slug it was installed under — a disabled
        // package must never reach ConnectorRegistry::register() at all.
        $this->expectException(InvalidArgumentException::class);
        app(ConnectorRegistry::class)->get('widget-status-fixture');
    }

    private function installFixture(string $fixtureDir, string $slug): void
    {
        File::copyDirectory(
            base_path("tests/Unit/Manager/fixtures/connectors/{$fixtureDir}"),
            storage_path("app/packages/{$slug}"),
        );

        $this->installedFixtureSlugs[] = $slug;
    }
}
