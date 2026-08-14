<?php

namespace App\Providers;

use App\Capabilities\CapabilityGateway;
use App\Capabilities\Providers\ConnectorBackedProvider;
use App\Connectors\ConnectorRegistry;
use App\Connectors\CurlHttpRequester;
use App\Connectors\HttpRequestContract;
use App\Connectors\PelicanConnector;
use App\Connectors\RestConnector;
use App\Experience\BlockRegistry;
use App\Experience\Blocks\GamesListBlock;
use App\Experience\Blocks\HeroBlock;
use App\Experience\Blocks\RichTextBlock;
use App\Experience\Blocks\ServerStatusBlock;
use App\Manager\CurlHttpClient;
use App\Manager\HttpClientContract;
use App\Models\Map;
use App\Normalizers\PalworldServerStatusNormalizer;
use App\Normalizers\PelicanServerStatusNormalizer;
use GamingHub\Core\Capabilities\CapabilityRouter;
use GamingHub\Core\Capabilities\Providers\ManualProvider;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Instance;
use GamingHub\Core\Models\Server;
use GamingHub\Core\Normalizers\NormalizerRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BlockRegistry::class);
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
        $registry = $this->app->make(BlockRegistry::class);

        $registry->register(RichTextBlock::class);
        $registry->register(GamesListBlock::class);
        $registry->register(ServerStatusBlock::class);
        $registry->register(HeroBlock::class);

        $connectors = $this->app->make(ConnectorRegistry::class);
        $connectors->register(RestConnector::class);
        $connectors->register(PelicanConnector::class);

        // Normalizers are always registered here — being *registered* isn't
        // the same as being *usable*. ConnectorBackedProvider checks the
        // owning InstalledPackage's enabled status at resolution time (see
        // ConnectorBackedProvider::PACKAGE_OWNED_NORMALIZERS), not here at
        // boot, since boot happens once per process/test and can't react to
        // an admin toggling enable/disable afterward.
        $normalizers = $this->app->make(NormalizerRegistry::class);
        $normalizers->register('palworld-server-status', new PalworldServerStatusNormalizer);
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
