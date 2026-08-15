<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ServerResource\RelationManagers\ProvidersRelationManager;
use App\Models\ConnectorInstance;
use App\Models\User;
use GamingHub\Core\Models\CapabilityBinding;
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

        $binding = CapabilityBinding::where('source_provider_id', $provider->id)->firstOrFail();
        $this->assertSame('server-status', $binding->capability);
        $this->assertSame($server->id, $binding->subject_id);
        $this->assertSame('pelican-server-status', $binding->value['normalizer']);
        $this->assertSame('d3aac351', $binding->value['call']['server_identifier']);
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
        $this->assertSame(['normalizer' => 'palworld-server-status'], $provider->config);

        $binding = CapabilityBinding::where('source_provider_id', $provider->id)->firstOrFail();
        $this->assertSame('/v1/api/metrics', $binding->value['call']['endpoint']);
    }

    public function test_deleting_a_provider_removes_its_capability_binding(): void
    {
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'rest']);
        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => ['normalizer' => 'palworld-server-status'],
        ]);
        CapabilityBinding::create([
            'capability' => 'server-status', 'subject_type' => 'server', 'subject_id' => $server->id,
            'provider' => 'connector', 'source_provider_id' => $provider->id, 'enabled' => true,
            'value' => ['connector_instance_id' => $connector->id, 'call' => ['endpoint' => '/v1/api/metrics'], 'normalizer' => 'palworld-server-status'],
        ]);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->callTableAction('delete', $provider);

        $this->assertDatabaseMissing('providers', ['id' => $provider->id]);
        $this->assertDatabaseMissing('capability_bindings', ['source_provider_id' => $provider->id]);
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
}
