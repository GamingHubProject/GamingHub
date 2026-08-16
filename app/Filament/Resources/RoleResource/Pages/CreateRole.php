<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\PermissionScope;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected array $pendingScopedPermissions = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingScopedPermissions = $data['scoped_permissions'] ?? [];
        unset($data['scoped_permissions']);

        return $data;
    }

    protected function afterCreate(): void
    {
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
