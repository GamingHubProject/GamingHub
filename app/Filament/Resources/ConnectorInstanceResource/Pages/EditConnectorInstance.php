<?php

namespace App\Filament\Resources\ConnectorInstanceResource\Pages;

use App\Filament\Resources\ConnectorInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConnectorInstance extends EditRecord
{
    protected static string $resource = ConnectorInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
