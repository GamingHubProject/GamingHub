<?php

namespace App\Filament\Widgets;

use Composer\InstalledVersions;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Instance;
use GamingHub\Core\Models\Server;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverviewWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        return [
            Stat::make('Games', Game::count())
                ->description(Game::where('status', 'enabled')->count().' enabled')
                ->icon('heroicon-o-rectangle-stack'),
            Stat::make('Servers', Server::count())
                ->description(Server::where('status', 'online')->count().' online')
                ->icon('heroicon-o-server'),
            Stat::make('Instances', Instance::count())
                ->icon('heroicon-o-squares-2x2'),
            Stat::make('Users', User::count())
                ->icon('heroicon-o-users'),
            Stat::make('Version', 'v'.config('app.version'))
                ->description('Core '.$this->coreVersion())
                ->icon('heroicon-o-tag')
                ->color('gray'),
        ];
    }

    protected function coreVersion(): string
    {
        try {
            return 'v'.ltrim(InstalledVersions::getPrettyVersion('gaminghubproject/core') ?? 'unknown', 'v');
        } catch (\OutOfBoundsException) {
            return 'unknown';
        }
    }
}
