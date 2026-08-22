<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/admin/system');

        $response->assertRedirect('/admin/system/login');
    }

    public function test_admin_can_access_dashboard(): void
    {
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin)->get('/admin/system');

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/system');

        $response->assertStatus(403);
    }

    public function test_bare_admin_path_is_the_spa_not_filament(): void
    {
        // Filament moved to /admin/system specifically to free this path
        // up for the SPA's own (in-progress) admin area — a guest here
        // should get the SPA shell, not Filament's login redirect.
        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertDontSee('filament', false);
    }
}
