<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ServerResource\RelationManagers\ProvidersRelationManager;
use App\Models\ConnectorInstance;
use App\Models\User;
use GamingHub\Core\Models\Capability;
use GamingHub\Core\Models\Provider;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProvidersRelationManagerTest extends TestCase
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

    public function test_can_add_a_pelican_provider_by_picking_a_discovered_uuid(): void
    {
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create([
            'type' => 'pelican',
            'discovered_servers' => [
                ['identifier' => 'd3aac351', 'name' => 'EU-1 Palworld'],
            ],
        ]);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'connector_instance_id' => $connector->id,
                'server_identifier' => 'd3aac351',
                'normalizer' => 'pelican-server-status',
                'status' => 'connected',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $provider = Provider::where('server_id', $server->id)->firstOrFail();
        $this->assertSame($connector->id, $provider->connector_instance_id);
        $this->assertSame('d3aac351', $provider->config['server_identifier']);
        $this->assertSame('connected', $provider->status);
        $this->assertSame('pelican-server-status', $provider->config['normalizer']);
        $this->assertSame('d3aac351', $provider->config['call']['server_identifier']);
    }

    public function test_can_add_a_rest_provider_without_a_uuid(): void
    {
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'rest']);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'connector_instance_id' => $connector->id,
                'normalizer' => 'palworld-server-status',
                'status' => 'connected',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $provider = Provider::where('server_id', $server->id)->firstOrFail();
        $this->assertSame($connector->id, $provider->connector_instance_id);
        $this->assertSame('palworld-server-status', $provider->config['normalizer']);
        $this->assertSame('/v1/api/metrics', $provider->config['call']['endpoint']);
    }

    public function test_deleting_a_provider_removes_it(): void
    {
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'rest']);
        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => ['normalizer' => 'palworld-server-status', 'call' => ['endpoint' => '/v1/api/metrics']],
        ]);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->callTableAction('delete', $provider);

        $this->assertDatabaseMissing('providers', ['id' => $provider->id]);
    }

    public function test_editing_a_provider_prefills_the_uuid(): void
    {
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create([
            'type' => 'pelican',
            'discovered_servers' => [
                ['identifier' => 'd3aac351', 'name' => 'EU-1 Palworld'],
            ],
        ]);
        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => ['server_identifier' => 'd3aac351'],
        ]);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->mountTableAction('edit', $provider)
            ->assertTableActionDataSet(['server_identifier' => 'd3aac351']);
    }

    public function test_priority_is_reorderable_and_defaults_to_zero(): void
    {
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'rest']);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'connector_instance_id' => $connector->id,
                'normalizer' => 'palworld-server-status',
                'status' => 'connected',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(0, Provider::where('server_id', $server->id)->firstOrFail()->priority);
    }

    public function test_can_add_a_manual_provider_from_the_capability_registry(): void
    {
        Capability::create(['id' => 'server-status', 'name' => 'Server Status']);
        $server = Server::factory()->create();

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'type' => 'manual',
                'capability' => 'server-status',
                'value' => ['online' => 'true', 'players' => '4'],
                'status' => 'connected',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $provider = Provider::where('server_id', $server->id)->firstOrFail();
        $this->assertSame('manual', $provider->type);
        $this->assertNull($provider->connector_instance_id);
        $this->assertSame('server-status', $provider->config['capability']);
        $this->assertSame('true', $provider->config['value']['online']);
    }

    public function test_editing_a_manual_provider_prefills_capability_and_value(): void
    {
        Capability::create(['id' => 'server-status', 'name' => 'Server Status']);
        $server = Server::factory()->create();
        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'type' => 'manual',
            'connector_instance_id' => null,
            'config' => ['capability' => 'server-status', 'value' => ['online' => 'true']],
        ]);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->mountTableAction('edit', $provider)
            ->assertTableActionDataSet([
                'capability' => 'server-status',
                'value' => ['online' => 'true'],
            ]);
    }
}
