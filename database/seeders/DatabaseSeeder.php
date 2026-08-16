<?php

namespace Database\Seeders;

use App\Models\User;
use GamingHub\Core\Database\Seeders\CapabilitySeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@local',
            'password' => bcrypt('local'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Admin');
    }
}
