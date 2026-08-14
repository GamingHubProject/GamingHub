<?php

namespace App\Providers;

use App\Capabilities\CapabilityGateway;
use App\Experience\BlockRegistry;
use App\Manager\CurlHttpClient;
use App\Manager\HttpClientContract;
use App\Experience\Blocks\GamesListBlock;
use App\Experience\Blocks\HeroBlock;
use App\Experience\Blocks\RichTextBlock;
use App\Experience\Blocks\ServerStatusBlock;
use App\Models\Map;
use GamingHub\Core\Capabilities\CapabilityRouter;
use GamingHub\Core\Capabilities\Providers\ManualProvider;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Instance;
use GamingHub\Core\Models\Server;
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
        $this->app->bind(HttpClientContract::class, CurlHttpClient::class);
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

        $this->app->make(CapabilityRouter::class)
            ->registerProvider(new ManualProvider);

        // Context Subjects a capability binding can be scoped to.
        Relation::morphMap([
            'game' => Game::class,
            'server' => Server::class,
            'instance' => Instance::class,
            'map' => Map::class,
        ]);
    }
}
