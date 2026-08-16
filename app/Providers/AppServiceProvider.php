<?php

namespace App\Providers;

use App\Capabilities\CapabilityGateway;
use App\Capabilities\Providers\ConnectorBackedProvider;
use App\Connectors\ConnectorRegistry;
use App\Connectors\CurlHttpRequester;
use App\Connectors\HttpRequestContract;
use App\Connectors\RestConnector;
use App\Manager\CurlHttpClient;
use App\Manager\HttpClientContract;
use App\Models\Map;
use GamingHub\Core\Capabilities\CapabilityRegistry;
use GamingHub\Core\Capabilities\CapabilityRouter;
use GamingHub\Core\Capabilities\Providers\ManualProvider;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Instance;
use GamingHub\Core\Models\Server;
use GamingHub\Core\Normalizers\FieldMappingNormalizer;
use GamingHub\Core\Normalizers\NormalizerRegistry;
use GamingHub\Core\Normalizers\PelicanServerStatusNormalizer;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CapabilityRegistry::class);
        $this->app->singleton(CapabilityRouter::class);
        $this->app->singleton(CapabilityGateway::class);
        $this->app->singleton(ConnectorRegistry::class);
        $this->app->singleton(NormalizerRegistry::class);
        $this->app->bind(HttpClientContract::class, CurlHttpClient::class);
        $this->app->bind(HttpRequestContract::class, CurlHttpRequester::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $connectors = $this->app->make(ConnectorRegistry::class);
        $connectors->register(RestConnector::class);

        // Pelican is no longer hardcoded here — it's registered as a
        // Connector package (storage/app/packages/pelican-connector/
        // connector.json) and loaded by PackageLoader instead, the same
        // way any other installed Connector would be. Deliberately NOT
        // called eagerly here: boot() runs on every artisan command,
        // including `migrate` on a brand-new database before
        // installed_packages exists yet — PackageLoader::loadConnectorPackages()
        // queries that table, so calling it unconditionally at boot would
        // break a fresh install. ConnectorRegistry::get() calls it lazily
        // on a cache miss instead, which only ever happens once real
        // capability resolution is underway (well after migrations).

        // Normalizers are always registered here — being *registered* isn't
        // the same as being *usable*. ConnectorBackedProvider checks the
        // owning InstalledPackage's enabled status at resolution time (see
        // ConnectorBackedProvider::PACKAGE_OWNED_NORMALIZERS), not here at
        // boot, since boot happens once per process/test and can't react to
        // an admin toggling enable/disable afterward. 'field-mapping' has
        // no package to gate on — it's Core's generic, always-available
        // normalizer for a REST connector against any game, replacing the
        // old hardcoded PalworldServerStatusNormalizer.
        $normalizers = $this->app->make(NormalizerRegistry::class);
        $normalizers->register('field-mapping', new FieldMappingNormalizer);
        $normalizers->register('pelican-server-status', new PelicanServerStatusNormalizer);

        $capabilityRouter = $this->app->make(CapabilityRouter::class);
        $capabilityRouter->registerProvider(new ManualProvider);
        $capabilityRouter->registerProvider($this->app->make(ConnectorBackedProvider::class));

        // Context Subjects a capability binding can be scoped to.
        Relation::morphMap([
            'game' => Game::class,
            'server' => Server::class,
            'instance' => Instance::class,
            'map' => Map::class,
        ]);
    }
}
