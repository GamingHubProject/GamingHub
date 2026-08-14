<?php

namespace App\Filament\Resources\ConfigurationPresetResource\Pages;

use App\Filament\Resources\ConfigurationPresetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConfigurationPreset extends EditRecord
{
    protected static string $resource = ConfigurationPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
