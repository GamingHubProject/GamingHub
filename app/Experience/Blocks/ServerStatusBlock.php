<?php

namespace App\Experience\Blocks;

use App\Capabilities\CapabilityGateway;
use App\Contracts\BlockContract;
use GamingHub\Core\Models\Server;
use Filament\Forms;
use Illuminate\Contracts\View\View;

/**
 * Reads the "server-status" capability through the Capability Gateway. If
 * this server has no Provider (and no manual default) answering it, the
 * gateway returns UNSUPPORTED — that's expected until an admin adds a
 * provider under the server's edit page, and is not an error state to fix
 * in this block.
 */
class ServerStatusBlock implements BlockContract
{
    public static function id(): string
    {
        return 'server-status';
    }

    public static function label(): string
    {
        return 'Server Status';
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
        $server = Server::find($config['server_id'] ?? null);

        $capability = $server
            ? app(CapabilityGateway::class)->get('server-status', $server)
            : null;

        return view('experience.blocks.server-status', [
            'server' => $server,
            'capability' => $capability,
        ]);
    }
}
