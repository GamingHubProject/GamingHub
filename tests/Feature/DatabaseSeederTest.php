<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_expected_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Role::count());
        foreach (['Admin', 'WebEditor', 'ContentEditor', 'User'] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
    }

    public function test_seeder_creates_admin_user_with_admin_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@local']);
        $admin = \App\Models\User::where('email', 'admin@local')->first();
        $this->assertTrue($admin->hasRole('Admin'));
    }
}
