<?php

namespace App\Filament\Resources\ServerGroupResource\Pages;

use App\Filament\Resources\ServerGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServerGroups extends ListRecords
{
    protected static string $resource = ServerGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
