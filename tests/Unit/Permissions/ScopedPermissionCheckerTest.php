<?php

namespace Tests\Unit\Permissions;

use App\Models\PermissionScope;
use App\Models\User;
use App\Permissions\ScopedPermissionChecker;
use GamingHub\Core\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScopedPermissionCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_role_with_no_scope_rows_is_unrestricted(): void
    {
        Permission::create(['name' => 'edit_pages', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);
        $role->givePermissionTo('edit_pages');
        $user = User::factory()->create();
        $user->assignRole('Editor');

        $checker = new ScopedPermissionChecker;

        $this->assertTrue($checker->can($user, 'edit_pages', null));
        $this->assertTrue($checker->can($user, 'edit_pages', 999));
        $this->assertNull($checker->visibleGameIds($user, 'edit_pages'));
    }

    public function test_a_role_scoped_to_one_game_cannot_act_on_another(): void
    {
        Permission::create(['name' => 'edit_pages', 'guard_name' => 'web']);
        $palworld = Game::factory()->create();
        $ark = Game::factory()->create();

        $role = Role::create(['name' => 'Palworld Editor', 'guard_name' => 'web']);
        $role->givePermissionTo('edit_pages');
        PermissionScope::create(['role_id' => $role->id, 'permission' => 'edit_pages', 'scope_type' => 'game', 'scope_id' => $palworld->id]);

        $user = User::factory()->create();
        $user->assignRole('Palworld Editor');

        $checker = new ScopedPermissionChecker;

        $this->assertTrue($checker->can($user, 'edit_pages', $palworld->id));
        $this->assertFalse($checker->can($user, 'edit_pages', $ark->id));
        $this->assertSame([$palworld->id], $checker->visibleGameIds($user, 'edit_pages'));
    }

    public function test_a_game_scoped_role_cannot_act_on_a_global_subject(): void
    {
        Permission::create(['name' => 'edit_pages', 'guard_name' => 'web']);
        $palworld = Game::factory()->create();

        $role = Role::create(['name' => 'Palworld Editor', 'guard_name' => 'web']);
        $role->givePermissionTo('edit_pages');
        PermissionScope::create(['role_id' => $role->id, 'permission' => 'edit_pages', 'scope_type' => 'game', 'scope_id' => $palworld->id]);

        $user = User::factory()->create();
        $user->assignRole('Palworld Editor');

        $this->assertFalse((new ScopedPermissionChecker)->can($user, 'edit_pages', null));
    }

    public function test_a_user_with_no_role_granting_the_permission_cannot_act_at_all(): void
    {
        Permission::create(['name' => 'edit_pages', 'guard_name' => 'web']);
        $user = User::factory()->create();

        $checker = new ScopedPermissionChecker;

        $this->assertFalse($checker->can($user, 'edit_pages', null));
        $this->assertSame([], $checker->visibleGameIds($user, 'edit_pages'));
    }

    public function test_two_scoped_roles_union_their_visible_games(): void
    {
        Permission::create(['name' => 'edit_pages', 'guard_name' => 'web']);
        $palworld = Game::factory()->create();
        $ark = Game::factory()->create();

        $palworldRole = Role::create(['name' => 'Palworld Editor', 'guard_name' => 'web']);
        $palworldRole->givePermissionTo('edit_pages');
        PermissionScope::create(['role_id' => $palworldRole->id, 'permission' => 'edit_pages', 'scope_type' => 'game', 'scope_id' => $palworld->id]);

        $arkRole = Role::create(['name' => 'Ark Editor', 'guard_name' => 'web']);
        $arkRole->givePermissionTo('edit_pages');
        PermissionScope::create(['role_id' => $arkRole->id, 'permission' => 'edit_pages', 'scope_type' => 'game', 'scope_id' => $ark->id]);

        $user = User::factory()->create();
        $user->assignRole(['Palworld Editor', 'Ark Editor']);

        $visible = (new ScopedPermissionChecker)->visibleGameIds($user, 'edit_pages');

        $this->assertEqualsCanonicalizing([$palworld->id, $ark->id], $visible);
    }
}
