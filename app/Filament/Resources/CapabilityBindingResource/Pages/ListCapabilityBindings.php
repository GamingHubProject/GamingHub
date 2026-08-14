<?php

namespace App\Filament\Resources\CapabilityBindingResource\Pages;

use App\Filament\Resources\CapabilityBindingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCapabilityBindings extends ListRecords
{
    protected static string $resource = CapabilityBindingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
