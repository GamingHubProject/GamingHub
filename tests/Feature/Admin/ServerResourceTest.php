<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ServerResource\Pages\CreateServer;
use App\Filament\Resources\ServerResource\Pages\ListServers;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServerResourceTest extends TestCase
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

    public function test_can_list_servers(): void
    {
        Server::factory()->count(3)->create();

        Livewire::test(ListServers::class)
            ->assertSuccessful();
    }

    public function test_can_create_server_for_game(): void
    {
        $game = Game::factory()->create(['has_servers' => true]);

        Livewire::test(CreateServer::class)
            ->fillForm([
                'game_id' => $game->id,
                'name' => 'Ragnarok',
                'slug' => 'ragnarok',
                'status' => 'online',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('servers', [
            'name' => 'Ragnarok',
            'game_id' => $game->id,
        ]);
    }

    public function test_server_belongs_to_game(): void
    {
        $game = Game::factory()->create();
        $server = Server::factory()->create(['game_id' => $game->id]);

        $this->assertTrue($server->game->is($game));
    }

    public function test_deleting_game_cascades_to_servers(): void
    {
        $game = Game::factory()->create();
        $server = Server::factory()->create(['game_id' => $game->id]);

        $game->delete();

        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
    }
}
