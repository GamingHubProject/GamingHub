<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_admin_user_with_role_and_seeded_roles(): void
    {
        $this->artisan('gaming-hub:admin', [
            '--name' => 'Rose',
            '--email' => 'rose@example.com',
            '--password' => 'super-secret',
        ])->assertSuccessful();

        $user = User::where('email', 'rose@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Admin'));
        $this->assertDatabaseHas('roles', ['name' => 'WebEditor']);
    }

    public function test_rerunning_promotes_existing_user_without_duplicating(): void
    {
        $this->artisan('gaming-hub:admin', [
            '--name' => 'Rose',
            '--email' => 'rose@example.com',
            '--password' => 'super-secret',
        ])->assertSuccessful();

        $this->artisan('gaming-hub:admin', [
            '--name' => 'Rose Updated',
            '--email' => 'rose@example.com',
            '--password' => 'another-secret',
        ])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'rose@example.com')->count());
        $this->assertSame('Rose Updated', User::where('email', 'rose@example.com')->first()->name);
    }

    public function test_rejects_short_password(): void
    {
        $this->artisan('gaming-hub:admin', [
            '--name' => 'Rose',
            '--email' => 'rose@example.com',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'rose@example.com']);
    }
}
