<?php

namespace App\Filament\Widgets;

use GamingHub\Core\Models\Server;
use Filament\Widgets\ChartWidget;

class ServersByStatusWidget extends ChartWidget
{
    protected static ?string $heading = 'Servers by status';

    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $counts = Server::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $labels = ['online' => 'Online', 'offline' => 'Offline', 'maintenance' => 'Maintenance'];

        return [
            'datasets' => [
                [
                    'data' => collect($labels)->keys()->map(fn (string $status) => $counts[$status] ?? 0)->all(),
                    'backgroundColor' => ['#22c55e', '#9ca3af', '#f59e0b'],
                ],
            ],
            'labels' => array_values($labels),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
