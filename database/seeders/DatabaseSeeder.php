<?php

namespace Database\Seeders;

use GamingHub\Core\Database\Seeders\CapabilitySeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Baseline, non-sensitive data only — roles and the capability vocabulary.
 * Deliberately never creates a user: this used to seed a hardcoded
 * admin@local/local account on every install, a publicly-known default
 * credential shipped by the installer's own printed output. Use
 * `php artisan gaming-hub:admin` for a real, interactively-prompted
 * administrator account instead — safe to run unconditionally, anywhere,
 * since it has no secrets of its own to leak.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // No PermissionSeeder anymore — every permission is now generated
        // per Game/ServerGroup/Server (GameObserver etc.), and Admin no
        // longer needs explicit grants at all (Gate::before in
        // AppServiceProvider + ScopedPermissionChecker's own Admin
        // short-circuit, since Spatie's hasPermissionTo() doesn't consult
        // Gate).
        $this->call(RoleSeeder::class);
        $this->call(CapabilitySeeder::class);
        // The built-in themes, so a fresh install opens the theme picker
        // on real options rather than an empty list. Idempotent by slug —
        // see BuiltInThemes::seed.
        $this->call(ThemeSeeder::class);
    }
}
