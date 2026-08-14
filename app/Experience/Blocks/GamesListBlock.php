<?php

namespace App\Experience\Blocks;

use App\Contracts\BlockContract;
use GamingHub\Core\Models\Game;
use Filament\Forms;
use Illuminate\Contracts\View\View;

class GamesListBlock implements BlockContract
{
    public static function id(): string
    {
        return 'games-list';
    }

    public static function label(): string
    {
        return 'Games List';
    }

    public static function configSchema(): array
    {
        return [
            Forms\Components\TextInput::make('limit')
                ->label('Number of games to show')
                ->numeric()
                ->default(6),
        ];
    }

    public function render(array $config): View
    {
        $limit = (int) ($config['limit'] ?? 6);

        return view('experience.blocks.games-list', [
            'games' => Game::query()
                ->where('status', 'enabled')
                ->orderBy('name')
                ->limit($limit)
                ->get(),
        ]);
    }
}
