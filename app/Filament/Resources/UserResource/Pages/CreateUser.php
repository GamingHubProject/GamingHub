<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\AdminAudit;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $roles = $this->record->roles()->pluck('name')->all();

        if (empty($roles)) {
            return;
        }

        AdminAudit::record('role_changed', 'User', $this->record->getKey(), [
            'added' => $roles,
            'removed' => [],
        ]);
    }
}
