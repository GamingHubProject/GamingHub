<?php

namespace Tests\Unit\Manager;

use App\Connectors\ConnectorRegistry;
use App\Manager\PackageLoader;
use App\Models\InstalledPackage;
use GamingHub\Core\Normalizers\NormalizerRegistry;
use GamingHubFixtures\FixturePanel\FixturePanelConnector;
use GamingHubFixtures\FixturePanel\FixturePanelStatusNormalizer;
use GamingHubFixtures\WidgetStatus\WidgetStatusConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Proves the previously-missing half of Manager: an enabled InstalledPackage
 * with a connector.json actually gets its PHP code loaded and registered —
 * into ConnectorRegistry for its connector class, and into
 * NormalizerRegistry for any normalizer(s) it declares. Two fixtures cover
 * two cases, both deliberately living NOWHERE Composer's own autoloader
 * reaches, proving PackageLoader's spl_autoload_register path actually
 * pulls in code from an installed package directory:
 *
 * - widget-status: connector only, no normalizers declared.
 * - fixture-panel: connector + a package-owned normalizer together — the
 *   same shape a real package like GamingHubProject/BasicConnectors ships
 *   (a Pelican-style connector paired with its own fixed-shape normalizer).
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

    public function test_loads_the_connector_and_registers_the_normalizer_it_declares(): void
    {
        $this->installFixture('fixture-panel', 'fixture-panel');
        InstalledPackage::factory()->create(['slug' => 'fixture-panel', 'status' => 'enabled']);

        app(PackageLoader::class)->loadConnectorPackages();

        $this->assertInstanceOf(FixturePanelConnector::class, app(ConnectorRegistry::class)->get('fixture-panel'));

        $normalizers = app(NormalizerRegistry::class);
        $this->assertTrue($normalizers->has('fixture-panel-status'));
        $this->assertInstanceOf(FixturePanelStatusNormalizer::class, $normalizers->get('fixture-panel-status'));
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
