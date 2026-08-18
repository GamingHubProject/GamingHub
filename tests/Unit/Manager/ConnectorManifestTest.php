<?php

namespace Tests\Unit\Manager;

use App\Manager\ConnectorManifest;
use App\Models\InstalledPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InstallsFixtureConnectorPackage;
use Tests\TestCase;

class ConnectorManifestTest extends TestCase
{
    use RefreshDatabase;
    use InstallsFixtureConnectorPackage;

    public function test_it_finds_the_package_that_owns_a_normalizer(): void
    {
        $this->installFixtureConnectorPackage();
        InstalledPackage::factory()->create(['slug' => 'fixture-panel', 'status' => 'enabled']);

        $slug = app(ConnectorManifest::class)->findOwningPackageSlug('fixture-panel-status');

        $this->assertSame('fixture-panel', $slug);
    }

    public function test_it_returns_null_for_a_normalizer_no_package_declares(): void
    {
        $slug = app(ConnectorManifest::class)->findOwningPackageSlug('field-mapping');

        $this->assertNull($slug);
    }

    public function test_it_reads_a_recommended_cadence_from_the_manifest(): void
    {
        $this->installFixtureConnectorPackage();
        InstalledPackage::factory()->create(['slug' => 'fixture-panel', 'status' => 'enabled']);

        $cadence = app(ConnectorManifest::class)->recommendedCadenceFor('fixture-panel-status');

        $this->assertSame(45, $cadence);
    }

    public function test_it_returns_null_when_no_package_recommends_a_cadence_for_that_normalizer(): void
    {
        $cadence = app(ConnectorManifest::class)->recommendedCadenceFor('field-mapping');

        $this->assertNull($cadence);
    }
}
