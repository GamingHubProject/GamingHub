<?php

namespace Tests\Unit\Permissions;

use App\Models\ServerGroup;
use App\Models\User;
use App\Permissions\ScopedPermissionChecker;
use App\Permissions\ScopedPermissionName;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScopedPermissionCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_direct_grant_on_a_game_allows_that_game(): void
    {
        $palworld = Game::factory()->create();
        $role = Role::create(['name' => 'Palworld Editor', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('game', $palworld->id, 'settings'));
        $user = User::factory()->create();
        $user->assignRole('Palworld Editor');

        $checker = new ScopedPermissionChecker;

        $this->assertTrue($checker->can($user, 'settings', $palworld));
    }

    public function test_a_role_scoped_to_one_game_cannot_act_on_another(): void
    {
        $palworld = Game::factory()->create();
        $ark = Game::factory()->create();

        $role = Role::create(['name' => 'Palworld Editor', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('game', $palworld->id, 'page'));
        $user = User::factory()->create();
        $user->assignRole('Palworld Editor');

        $checker = new ScopedPermissionChecker;

        $this->assertTrue($checker->can($user, 'page', $palworld));
        $this->assertFalse($checker->can($user, 'page', $ark));
    }

    public function test_a_game_level_grant_cascades_down_to_its_server_groups_and_servers(): void
    {
        $palworld = Game::factory()->create();
        $group = ServerGroup::factory()->create(['game_id' => $palworld->id]);
        $server = Server::factory()->create(['game_id' => $palworld->id, 'server_group_id' => $group->id]);

        $role = Role::create(['name' => 'Palworld Editor', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('game', $palworld->id, 'settings'));
        $user = User::factory()->create();
        $user->assignRole('Palworld Editor');

        $checker = new ScopedPermissionChecker;

        $this->assertTrue($checker->can($user, 'settings', $group));
        $this->assertTrue($checker->can($user, 'settings', $server));
    }

    public function test_a_server_group_grant_does_not_cover_a_sibling_group_in_the_same_game(): void
    {
        $palworld = Game::factory()->create();
        $groupA = ServerGroup::factory()->create(['game_id' => $palworld->id]);
        $groupB = ServerGroup::factory()->create(['game_id' => $palworld->id]);

        $role = Role::create(['name' => 'Group A Editor', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('servergroup', $groupA->id, 'settings'));
        $user = User::factory()->create();
        $user->assignRole('Group A Editor');

        $checker = new ScopedPermissionChecker;

        $this->assertTrue($checker->can($user, 'settings', $groupA));
        $this->assertFalse($checker->can($user, 'settings', $groupB));
    }

    public function test_a_narrower_server_grant_does_not_widen_to_the_whole_game(): void
    {
        $palworld = Game::factory()->create();
        $group = ServerGroup::factory()->create(['game_id' => $palworld->id]);
        $server = Server::factory()->create(['game_id' => $palworld->id, 'server_group_id' => $group->id]);

        $role = Role::create(['name' => 'Server Editor', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('server', $server->id, 'settings'));
        $user = User::factory()->create();
        $user->assignRole('Server Editor');

        $checker = new ScopedPermissionChecker;

        $this->assertTrue($checker->can($user, 'settings', $server));
        $this->assertFalse($checker->can($user, 'settings', $group));
        $this->assertFalse($checker->can($user, 'settings', $palworld));
    }

    public function test_admin_can_act_on_anything_with_no_explicit_grants(): void
    {
        $palworld = Game::factory()->create();
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->assertTrue((new ScopedPermissionChecker)->can($admin, 'settings', $palworld));
    }

    public function test_a_user_with_no_role_granting_the_permission_cannot_act_at_all(): void
    {
        $palworld = Game::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse((new ScopedPermissionChecker)->can($user, 'settings', $palworld));
    }

    public function test_visible_ids_reflects_cascading_grants_at_the_requested_level(): void
    {
        $palworld = Game::factory()->create();
        $ark = Game::factory()->create();
        $palworldGroup = ServerGroup::factory()->create(['game_id' => $palworld->id]);
        $arkGroup = ServerGroup::factory()->create(['game_id' => $ark->id]);

        $role = Role::create(['name' => 'Palworld Editor', 'guard_name' => 'web']);
        $role->givePermissionTo(ScopedPermissionName::for('game', $palworld->id, 'settings'));
        $user = User::factory()->create();
        $user->assignRole('Palworld Editor');

        $checker = new ScopedPermissionChecker;

        $this->assertSame([$palworld->id], $checker->visibleIds($user, 'settings', 'game'));
        $this->assertSame([$palworldGroup->id], $checker->visibleIds($user, 'settings', 'servergroup'));
        $this->assertNotContains($arkGroup->id, $checker->visibleIds($user, 'settings', 'servergroup'));
    }

    public function test_visible_ids_is_empty_for_a_user_with_no_grants(): void
    {
        Game::factory()->create();
        $user = User::factory()->create();

        $this->assertSame([], (new ScopedPermissionChecker)->visibleIds($user, 'settings', 'game'));
    }
}
