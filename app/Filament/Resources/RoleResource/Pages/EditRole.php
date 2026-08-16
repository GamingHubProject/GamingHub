<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\PermissionScope;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected array $pendingScopedPermissions = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['scoped_permissions'] = PermissionScope::query()
            ->where('role_id', $this->record->id)
            ->get()
            ->map(fn (PermissionScope $scope) => [
                'permission' => $scope->permission,
                'game_id' => $scope->scope_id,
            ])
            ->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingScopedPermissions = $data['scoped_permissions'] ?? [];
        unset($data['scoped_permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        PermissionScope::where('role_id', $this->record->id)->delete();

        foreach ($this->pendingScopedPermissions as $scope) {
            if (empty($scope['permission']) || empty($scope['game_id'])) {
                continue;
            }

            PermissionScope::create([
                'role_id' => $this->record->id,
                'permission' => $scope['permission'],
                'scope_type' => 'game',
                'scope_id' => $scope['game_id'],
            ]);
        }
    }
}
