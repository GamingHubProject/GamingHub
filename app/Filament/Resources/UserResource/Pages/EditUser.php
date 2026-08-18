<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\AdminAudit;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * Role assignment can't be audited via the generic AdminAuditObserver —
 * UserResource's roles field is a Filament relationship() multi-select,
 * which saves by calling BelongsToMany::sync() directly on the pivot
 * table, bypassing Eloquent model events entirely (and bypassing
 * Spatie's own RoleAttached/RoleDetached events too, since those only
 * fire from Spatie's own assignRole()/syncRoles() helpers, never called
 * here). beforeSave()/afterSave() bracket Filament's own save, so
 * $rolesBeforeSave is captured from the database before Filament's sync
 * runs, then diffed against the post-save state.
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $rolesBeforeSave = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->rolesBeforeSave = $this->record->roles()->pluck('name')->all();
    }

    protected function afterSave(): void
    {
        $after = $this->record->roles()->pluck('name')->all();

        $added = array_values(array_diff($after, $this->rolesBeforeSave));
        $removed = array_values(array_diff($this->rolesBeforeSave, $after));

        if (empty($added) && empty($removed)) {
            return;
        }

        AdminAudit::record('role_changed', 'User', $this->record->getKey(), [
            'added' => $added,
            'removed' => $removed,
        ]);
    }
}
