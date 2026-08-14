<?php

namespace App\Filament\Resources\ConnectorInstanceResource\Pages;

use App\Filament\Resources\ConnectorInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConnectorInstances extends ListRecords
{
    protected static string $resource = ConnectorInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
