<?php

namespace App\Permissions;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

/**
 * Creates/removes the 4 scoped permissions for one Game/ServerGroup/Server
 * row. Shared by the model Observers (real-time, on create/delete) and the
 * `permissions:sync-scoped` command (backfill for rows that already
 * existed before the observer was wired up) so the actual generation logic
 * only lives in one place.
 */
class ScopedPermissionGenerator
{
    public function generateFor(Model $entity, string $entityType): void
    {
        foreach (ScopedPermissionName::allFor($entityType, $entity->getKey()) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function deleteFor(Model $entity, string $entityType): void
    {
        Permission::query()
            ->whereIn('name', ScopedPermissionName::allFor($entityType, $entity->getKey()))
            ->where('guard_name', 'web')
            ->delete();
    }
}
