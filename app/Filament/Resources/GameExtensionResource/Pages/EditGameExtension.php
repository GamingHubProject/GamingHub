<?php

namespace App\Filament\Resources\GameExtensionResource\Pages;

use App\Filament\Resources\GameExtensionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGameExtension extends EditRecord
{
    protected static string $resource = GameExtensionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
