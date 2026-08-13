<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAndRoleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
    }

    public function test_can_list_users(): void
    {
        $response = $this->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_users_index_component_renders(): void
    {
        User::factory()->count(3)->create();

        Livewire::test(ListUsers::class)
            ->assertSuccessful();
    }

    public function test_can_assign_role_to_user(): void
    {
        $user = User::factory()->create();

        $user->assignRole('Admin');

        $this->assertTrue($user->fresh()->hasRole('Admin'));
    }

    public function test_can_list_roles(): void
    {
        Role::create(['name' => 'WebEditor', 'guard_name' => 'web']);

        Livewire::test(ListRoles::class)
            ->assertSuccessful();
    }
}
