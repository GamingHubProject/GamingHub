<?php

namespace App\Filament\Resources\ServerGroupResource\Pages;

use App\Filament\Resources\ServerGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServerGroup extends EditRecord
{
    protected static string $resource = ServerGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
