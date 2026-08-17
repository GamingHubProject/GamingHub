<?php

namespace Tests\Feature\Admin;

use App\Connectors\HttpRequestContract;
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
use Tests\Unit\Connectors\Support\FakeHttpRequester;

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
                'status' => 'connected',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $provider = Provider::where('server_id', $server->id)->firstOrFail();
        $this->assertSame($connector->id, $provider->connector_instance_id);
        $this->assertSame('d3aac351', $provider->config['server_identifier']);
        $this->assertSame('connected', $provider->status);
        // Pelican's normalizer is implied by the connector's own type, not
        // chosen by the admin — there's exactly one way Pelican reports
        // status, unlike a generic REST connector.
        $this->assertSame('pelican-server-status', $provider->config['normalizer']);
        $this->assertSame('d3aac351', $provider->config['call']['server_identifier']);
    }

    public function test_can_add_a_rest_provider_with_an_admin_defined_field_mapping(): void
    {
        Capability::create(['id' => 'server-status', 'name' => 'Server Status']);
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'rest']);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'connector_instance_id' => $connector->id,
                'endpoint' => '/v1/api/metrics',
                'capability' => 'server-status',
                'field_map' => ['currentplayernum' => 'players'],
                'status' => 'connected',
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $provider = Provider::where('server_id', $server->id)->firstOrFail();
        $this->assertSame($connector->id, $provider->connector_instance_id);
        $this->assertSame('field-mapping', $provider->config['normalizer']);
        $this->assertSame('server-status', $provider->config['capability']);
        $this->assertSame('/v1/api/metrics', $provider->config['call']['endpoint']);
        $this->assertSame('players', $provider->config['field_map']['currentplayernum']);
    }

    public function test_deleting_a_provider_removes_it(): void
    {
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'rest']);
        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
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

    public function test_editing_a_rest_provider_prefills_capability_endpoint_and_field_map(): void
    {
        Capability::create(['id' => 'server-status', 'name' => 'Server Status']);
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'rest']);
        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->mountTableAction('edit', $provider)
            ->assertTableActionDataSet([
                'capability' => 'server-status',
                'endpoint' => '/v1/api/metrics',
                'field_map' => ['currentplayernum' => 'players'],
            ]);
    }

    public function test_priority_is_reorderable_and_defaults_to_zero(): void
    {
        Capability::create(['id' => 'server-status', 'name' => 'Server Status']);
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'rest']);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->mountTableAction('create')
            ->setTableActionData([
                'connector_instance_id' => $connector->id,
                'endpoint' => '/v1/api/metrics',
                'capability' => 'server-status',
                'field_map' => ['currentplayernum' => 'players'],
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

    public function test_the_test_action_reports_success_and_clears_a_previous_error(): void
    {
        Capability::create(['id' => 'server-status', 'name' => 'Server Status']);
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'rest']);
        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'status' => 'error',
            'error_message' => 'stale failure from before',
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['currentplayernum' => 6]));
        $this->app->instance(HttpRequestContract::class, $fake);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->callTableAction('test', $provider);

        $provider->refresh();
        $this->assertSame('connected', $provider->status);
        $this->assertNull($provider->error_message);
    }

    public function test_the_test_action_reports_and_persists_a_failure_reason(): void
    {
        Capability::create(['id' => 'server-status', 'name' => 'Server Status']);
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'rest', 'base_url' => 'http://unreachable']);
        $provider = Provider::factory()->create([
            'server_id' => $server->id,
            'connector_instance_id' => $connector->id,
            'config' => [
                'normalizer' => 'field-mapping',
                'capability' => 'server-status',
                'call' => ['endpoint' => '/v1/api/metrics'],
                'field_map' => ['currentplayernum' => 'players'],
            ],
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(500, 'Internal Server Error');
        $this->app->instance(HttpRequestContract::class, $fake);

        Livewire::test(ProvidersRelationManager::class, [
            'ownerRecord' => $server,
            'pageClass' => \App\Filament\Resources\ServerResource\Pages\EditServer::class,
        ])
            ->callTableAction('test', $provider);

        $provider->refresh();
        $this->assertSame('error', $provider->status);
        $this->assertStringContainsString('HTTP 500', $provider->error_message);
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
