<?php

namespace App\Filament\Resources\InstalledPackageResource\Pages;

use App\Filament\Resources\InstalledPackageResource;
use App\Manager\PackageInstaller;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListInstalledPackages extends ListRecords
{
    protected static string $resource = InstalledPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('installFromRegistry')
                ->label('Install from registry')
                ->icon('heroicon-o-arrow-down-tray')
                ->form([
                    Forms\Components\TextInput::make('registry_url')
                        ->label('Registry URL')
                        ->required()
                        ->url()
                        ->default('https://raw.githubusercontent.com/GamingHubProject/Registry/main/extension_registry.json')
                        ->helperText('extension_registry.json for Hub Extensions, or a games_registry.json for Game Integrations.'),
                    Forms\Components\TextInput::make('package_id')
                        ->label('Package ID')
                        ->required()
                        ->helperText('Must match an "id" in that registry\'s packages list.'),
                    Forms\Components\TextInput::make('version')
                        ->required()
                        ->helperText('The release tag to install, e.g. "0.1.000" — Manager doesn\'t guess "latest".'),
                ])
                ->action(function (array $data, PackageInstaller $installer): void {
                    $result = $installer->install($data['registry_url'], $data['package_id'], $data['version']);

                    Notification::make()
                        ->title($result['status'] === 'ok' ? 'Package installed' : 'Install failed')
                        ->body($result['message'])
                        ->{$result['status'] === 'ok' ? 'success' : 'danger'}()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
