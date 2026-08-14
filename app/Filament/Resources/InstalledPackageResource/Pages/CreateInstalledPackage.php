<?php

namespace App\Filament\Resources\InstalledPackageResource\Pages;

use App\Filament\Resources\InstalledPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInstalledPackage extends CreateRecord
{
    protected static string $resource = InstalledPackageResource::class;
}
