<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ServerResource\Pages\CreateServer;
use App\Filament\Resources\ServerResource\Pages\EditServer;
use App\Filament\Resources\ServerResource\Pages\ListServers;
use App\Models\ConnectorInstance;
use App\Models\User;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
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

    public function test_live_stat_fields_are_editable_when_a_server_has_no_connector_provider(): void
    {
        $server = Server::factory()->create();

        Livewire::test(EditServer::class, ['record' => $server->id])
            ->assertFormFieldIsEnabled('status')
            ->assertFormFieldIsEnabled('current_players')
            ->assertFormFieldIsEnabled('max_players')
            ->assertFormFieldIsEnabled('cpu_usage_percent')
            ->assertFormFieldIsEnabled('memory_usage_bytes');
    }

    public function test_live_stat_fields_are_editable_when_a_server_only_has_a_manual_provider(): void
    {
        $server = Server::factory()->create();
        Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => null,
            'type' => 'manual',
            'config' => ['capability' => 'server-status', 'value' => ['online' => 'true']],
        ]);

        Livewire::test(EditServer::class, ['record' => $server->id])
            ->assertFormFieldIsEnabled('status');
    }

    public function test_live_stat_fields_are_disabled_when_a_server_has_a_connector_provider(): void
    {
        $server = Server::factory()->create();
        $connector = ConnectorInstance::create([
            'name' => 'Game REST API',
            'type' => 'rest',
            'base_url' => 'http://game-server:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
            'poll_interval_seconds' => 30,
        ]);
        Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'type' => 'connector',
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);

        Livewire::test(EditServer::class, ['record' => $server->id])
            ->assertFormFieldIsDisabled('status')
            ->assertFormFieldIsDisabled('current_players')
            ->assertFormFieldIsDisabled('max_players')
            ->assertFormFieldIsDisabled('cpu_usage_percent')
            ->assertFormFieldIsDisabled('memory_usage_bytes');
    }
}
