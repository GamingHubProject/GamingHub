<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\AdminAudit;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Permission changes can't be audited via the generic AdminAuditObserver
 * for the same reason User's role assignment can't — syncPermissions()
 * writes the permission pivot table directly, bypassing Eloquent model
 * events (and Spatie's own PermissionAttached/PermissionDetached events,
 * which only fire from different call paths than this one). Same
 * before/after-diff shape as EditUser.
 */
class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected array $pendingScopedPermissionNames = [];

    protected array $permissionsBeforeSave = [];

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

    protected function beforeSave(): void
    {
        $this->permissionsBeforeSave = $this->record->permissions()->pluck('name')->all();
    }

    protected function afterSave(): void
    {
        $this->record->syncPermissions($this->pendingScopedPermissionNames);

        $added = array_values(array_diff($this->pendingScopedPermissionNames, $this->permissionsBeforeSave));
        $removed = array_values(array_diff($this->permissionsBeforeSave, $this->pendingScopedPermissionNames));

        if (empty($added) && empty($removed)) {
            return;
        }

        AdminAudit::record('permissions_changed', 'Role', $this->record->getKey(), [
            'added' => $added,
            'removed' => $removed,
        ]);
    }
}
