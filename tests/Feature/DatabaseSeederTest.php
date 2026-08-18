<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use GamingHub\Core\Models\Capability;
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

    public function test_seeder_creates_baseline_capabilities(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, Capability::count());
        $this->assertDatabaseHas('capabilities', ['id' => 'server-status']);
    }

    /**
     * DatabaseSeeder used to also create a hardcoded admin@local/local
     * account — a publicly-known default credential printed directly in
     * the installer's own output. It creates no user at all now;
     * `php artisan gaming-hub:admin` (interactive, real credentials) is
     * the only way an administrator account gets created.
     */
    public function test_seeder_creates_no_user_at_all(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }
}
