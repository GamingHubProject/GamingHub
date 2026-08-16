<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['edit_pages', 'see_drafts'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Admin is unrestricted by convention throughout this app (see
        // CreateAdminCommand) — give it both permissions globally so the
        // system is usable out of the box; no PermissionScope rows means
        // no restriction.
        Role::where('name', 'Admin')->first()?->givePermissionTo(['edit_pages', 'see_drafts']);
    }
}
