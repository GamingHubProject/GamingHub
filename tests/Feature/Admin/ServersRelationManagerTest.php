<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ServerGroupResource\Pages\EditServerGroup;
use App\Filament\Resources\ServerGroupResource\RelationManagers\ServersRelationManager;
use App\Models\ServerGroup;
use App\Models\User;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServersRelationManagerTest extends TestCase
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

    public function test_it_lists_the_servers_belonging_to_the_group(): void
    {
        $group = ServerGroup::factory()->create();
        $inGroup = Server::factory()->create(['game_id' => $group->game_id, 'server_group_id' => $group->id]);
        $otherGroup = ServerGroup::factory()->create();
        $notInGroup = Server::factory()->create(['game_id' => $otherGroup->game_id, 'server_group_id' => $otherGroup->id]);

        Livewire::test(ServersRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditServerGroup::class,
        ])
            ->assertCanSeeTableRecords([$inGroup])
            ->assertCanNotSeeTableRecords([$notInGroup]);
    }

    public function test_it_shows_status_and_player_counts(): void
    {
        $group = ServerGroup::factory()->create();
        $server = Server::factory()->create([
            'game_id' => $group->game_id,
            'server_group_id' => $group->id,
            'status' => 'online',
            'current_players' => 5,
            'max_players' => 32,
        ]);

        Livewire::test(ServersRelationManager::class, [
            'ownerRecord' => $group,
            'pageClass' => EditServerGroup::class,
        ])
            ->assertSee($server->name)
            ->assertSee('5/32');
    }
}
