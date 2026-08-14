<?php

namespace App\Providers;

use App\Experience\BlockRegistry;
use App\Experience\Blocks\GamesListBlock;
use App\Experience\Blocks\RichTextBlock;
use App\Experience\Blocks\ServerStatusBlock;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BlockRegistry::class);
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
    }
}
