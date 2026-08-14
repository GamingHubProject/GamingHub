<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ServerGroupResource\Pages\CreateServerGroup;
use App\Filament\Resources\ServerGroupResource\Pages\ListServerGroups;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use App\Models\ServerGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServerGroupResourceTest extends TestCase
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

    public function test_can_list_server_groups(): void
    {
        ServerGroup::factory()->count(2)->create();

        Livewire::test(ListServerGroups::class)->assertSuccessful();
    }

    public function test_can_create_server_group(): void
    {
        $game = Game::factory()->create();

        Livewire::test(CreateServerGroup::class)
            ->fillForm([
                'game_id' => $game->id,
                'name' => 'ARK Cluster',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('server_groups', ['name' => 'ARK Cluster', 'game_id' => $game->id]);
    }

    public function test_servers_can_belong_to_a_group(): void
    {
        $group = ServerGroup::factory()->create();
        $server = Server::factory()->create(['game_id' => $group->game_id, 'server_group_id' => $group->id]);

        $this->assertSame($group->id, $server->server_group_id);
        $this->assertTrue($group->servers->contains($server));
    }

    public function test_server_group_is_optional_for_a_server(): void
    {
        $server = Server::factory()->create(['server_group_id' => null]);

        $this->assertNull($server->server_group_id);
    }
}
