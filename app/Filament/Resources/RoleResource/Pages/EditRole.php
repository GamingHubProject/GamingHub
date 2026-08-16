<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected array $pendingScopedPermissionNames = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...RoleResource::scopedFieldDefaults($this->record)];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingScopedPermissionNames = RoleResource::extractCheckedPermissionNames($data);

        return collect($data)->reject(fn ($value, $key) => str_starts_with($key, 'scoped_'))->all();
    }

    protected function afterSave(): void
    {
        $this->record->syncPermissions($this->pendingScopedPermissionNames);
    }
}
