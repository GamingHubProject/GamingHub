<?php

namespace Tests\Feature;

use App\Models\ServerGroup;
use App\Permissions\ScopedPermissionName;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ScopedPermissionGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_game_generates_its_four_scoped_permissions(): void
    {
        $game = Game::factory()->create();

        foreach (ScopedPermissionName::TYPES as $type) {
            $this->assertDatabaseHas('permissions', [
                'name' => ScopedPermissionName::for('game', $game->id, $type),
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_deleting_a_game_removes_its_four_scoped_permissions(): void
    {
        $game = Game::factory()->create();
        $names = ScopedPermissionName::allFor('game', $game->id);

        $game->delete();

        foreach ($names as $name) {
            $this->assertDatabaseMissing('permissions', ['name' => $name]);
        }
    }

    public function test_creating_a_server_group_generates_its_four_scoped_permissions(): void
    {
        $group = ServerGroup::factory()->create();

        foreach (ScopedPermissionName::TYPES as $type) {
            $this->assertDatabaseHas('permissions', [
                'name' => ScopedPermissionName::for('servergroup', $group->id, $type),
            ]);
        }
    }

    public function test_creating_a_server_generates_its_four_scoped_permissions(): void
    {
        $server = Server::factory()->create();

        foreach (ScopedPermissionName::TYPES as $type) {
            $this->assertDatabaseHas('permissions', [
                'name' => ScopedPermissionName::for('server', $server->id, $type),
            ]);
        }
    }

    public function test_sync_scoped_permissions_backfills_rows_that_predate_the_observer(): void
    {
        // withoutEvents() so the observer never fires — simulates a Game
        // that already existed in the database before GameObserver was
        // wired up, which the command exists specifically to catch up.
        $game = Game::withoutEvents(fn () => Game::factory()->create());

        $this->assertDatabaseMissing('permissions', [
            'name' => ScopedPermissionName::for('game', $game->id, 'settings'),
        ]);

        $this->artisan('permissions:sync-scoped')->assertSuccessful();

        foreach (ScopedPermissionName::TYPES as $type) {
            $this->assertDatabaseHas('permissions', [
                'name' => ScopedPermissionName::for('game', $game->id, $type),
            ]);
        }
    }

    public function test_sync_scoped_permissions_is_idempotent(): void
    {
        $game = Game::factory()->create();
        $countAfterCreate = Permission::count();

        $this->artisan('permissions:sync-scoped')->assertSuccessful();

        $this->assertSame($countAfterCreate, Permission::count());
    }
}
