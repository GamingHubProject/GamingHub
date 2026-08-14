<?php

namespace App\Filament\Resources\GameExtensionResource\Pages;

use App\Filament\Resources\GameExtensionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGameExtensions extends ListRecords
{
    protected static string $resource = GameExtensionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
