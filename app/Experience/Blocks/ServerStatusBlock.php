<?php

namespace App\Experience\Blocks;

use App\Contracts\BlockContract;
use App\Models\Server;
use Filament\Forms;
use Illuminate\Contracts\View\View;

/**
 * Shows the Server's last-known status field as stored in the database.
 * This is NOT a live capability probe — the Capability Gateway (Milestone 4)
 * doesn't exist yet, so this only reflects whatever was last saved.
 */
class ServerStatusBlock implements BlockContract
{
    public static function id(): string
    {
        return 'server-status';
    }

    public static function label(): string
    {
        return 'Server Status (static)';
    }

    public static function configSchema(): array
    {
        return [
            Forms\Components\Select::make('server_id')
                ->label('Server')
                ->relationship('server', 'name')
                ->searchable()
                ->required(),
        ];
    }

    public function render(array $config): View
    {
        return view('experience.blocks.server-status', [
            'server' => Server::find($config['server_id'] ?? null),
        ]);
    }
}
