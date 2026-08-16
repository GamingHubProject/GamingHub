<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\PermissionScope;
use App\Models\User;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
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

    public function test_can_create_a_role_with_a_game_scoped_permission_restriction(): void
    {
        Permission::create(['name' => 'edit_pages', 'guard_name' => 'web']);
        $palworld = Game::factory()->create();

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'Palworld Editor',
                'guard_name' => 'web',
                'permissions' => [],
                'scoped_permissions' => [
                    ['permission' => 'edit_pages', 'game_id' => $palworld->id],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'Palworld Editor')->firstOrFail();
        $this->assertDatabaseHas('permission_scopes', [
            'role_id' => $role->id,
            'permission' => 'edit_pages',
            'scope_type' => 'game',
            'scope_id' => $palworld->id,
        ]);
    }

    public function test_editing_a_role_replaces_its_scoped_permissions(): void
    {
        Permission::create(['name' => 'edit_pages', 'guard_name' => 'web']);
        $palworld = Game::factory()->create();
        $ark = Game::factory()->create();
        $role = Role::create(['name' => 'Scoped Editor', 'guard_name' => 'web']);
        PermissionScope::create(['role_id' => $role->id, 'permission' => 'edit_pages', 'scope_type' => 'game', 'scope_id' => $palworld->id]);

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->fillForm([
                'scoped_permissions' => [
                    ['permission' => 'edit_pages', 'game_id' => $ark->id],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('permission_scopes', ['role_id' => $role->id, 'scope_id' => $palworld->id]);
        $this->assertDatabaseHas('permission_scopes', ['role_id' => $role->id, 'scope_id' => $ark->id]);
    }
}
