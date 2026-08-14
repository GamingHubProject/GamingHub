<?php

namespace App\Filament\Resources\InstalledPackageResource\Pages;

use App\Filament\Pages\BrowseRegistry;
use App\Filament\Resources\InstalledPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInstalledPackages extends ListRecords
{
    protected static string $resource = InstalledPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('browseRegistry')
                ->label('Browse registry')
                ->icon('heroicon-o-globe-alt')
                ->url(BrowseRegistry::getUrl()),
            Actions\CreateAction::make()
                ->label('Add record manually'),
        ];
    }
}
