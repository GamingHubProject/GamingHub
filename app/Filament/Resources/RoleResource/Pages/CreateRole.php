<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected array $pendingScopedPermissionNames = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingScopedPermissionNames = RoleResource::extractCheckedPermissionNames($data);

        return collect($data)->reject(fn ($value, $key) => str_starts_with($key, 'scoped_'))->all();
    }

    protected function afterCreate(): void
    {
        // syncPermissions() rather than givePermissionTo() in a loop: since
        // every permission in the system is now one of these scoped ones,
        // the checked set IS the role's complete desired permission list.
        $this->record->syncPermissions($this->pendingScopedPermissionNames);
    }
}
