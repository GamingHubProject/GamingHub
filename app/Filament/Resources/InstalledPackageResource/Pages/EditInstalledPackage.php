<?php

namespace App\Filament\Resources\InstalledPackageResource\Pages;

use App\Filament\Resources\InstalledPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInstalledPackage extends EditRecord
{
    protected static string $resource = InstalledPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
