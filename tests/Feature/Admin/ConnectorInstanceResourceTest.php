<?php

namespace Tests\Feature\Admin;

use App\Connectors\HttpRequestContract;
use App\Filament\Resources\ConnectorInstanceResource\Pages\CreateConnectorInstance;
use App\Filament\Resources\ConnectorInstanceResource\Pages\ListConnectorInstances;
use App\Models\ConnectorInstance;
use App\Models\InstalledPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InstallsFixtureConnectorPackage;
use Tests\TestCase;
use Tests\Unit\Connectors\Support\FakeHttpRequester;

class ConnectorInstanceResourceTest extends TestCase
{
    use RefreshDatabase;
    use InstallsFixtureConnectorPackage;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
    }

    public function test_can_create_a_pelican_connector_with_both_keys(): void
    {
        Livewire::test(CreateConnectorInstance::class)
            ->fillForm([
                'name' => 'Our Pelican',
                'type' => 'pelican',
                'base_url' => 'https://panel.test',
                'pelican_application_token' => 'ptla_admin',
                'pelican_client_token' => 'ptlc_user',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('connector_instances', ['name' => 'Our Pelican', 'type' => 'pelican']);
        $connector = ConnectorInstance::where('name', 'Our Pelican')->firstOrFail();
        $this->assertSame(
            ['application_token' => 'ptla_admin', 'client_token' => 'ptlc_user'],
            $connector->credentials
        );
    }

    public function test_can_create_a_pelican_connector_with_only_the_application_key(): void
    {
        Livewire::test(CreateConnectorInstance::class)
            ->fillForm([
                'name' => 'Our Pelican',
                'type' => 'pelican',
                'base_url' => 'https://panel.test',
                'pelican_application_token' => 'ptla_admin',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $connector = ConnectorInstance::where('name', 'Our Pelican')->firstOrFail();
        $this->assertSame('ptla_admin', $connector->credentials['application_token']);
        $this->assertNull($connector->credentials['client_token']);
    }

    public function test_can_create_a_rest_connector_with_basic_auth(): void
    {
        Livewire::test(CreateConnectorInstance::class)
            ->fillForm([
                'name' => 'Our Palworld',
                'type' => 'rest',
                'base_url' => 'http://palworld:8212',
                'rest_auth_style' => 'basic',
                'rest_username' => 'admin',
                'rest_password' => 'secret',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $connector = ConnectorInstance::where('name', 'Our Palworld')->firstOrFail();
        $this->assertSame(['username' => 'admin', 'password' => 'secret'], $connector->credentials);
    }

    public function test_can_create_a_rest_connector_with_bearer_auth(): void
    {
        Livewire::test(CreateConnectorInstance::class)
            ->fillForm([
                'name' => 'Our API',
                'type' => 'rest',
                'base_url' => 'https://api.test',
                'rest_auth_style' => 'bearer',
                'rest_token' => 'abc123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $connector = ConnectorInstance::where('name', 'Our API')->firstOrFail();
        $this->assertSame(['token' => 'abc123'], $connector->credentials);
    }

    public function test_editing_a_connector_prefills_the_right_typed_fields(): void
    {
        $connector = ConnectorInstance::create([
            'name' => 'Our Pelican',
            'type' => 'pelican',
            'base_url' => 'https://panel.test',
            'credentials' => ['application_token' => 'ptla_admin', 'client_token' => 'ptlc_user'],
            'status' => 'untested',
        ]);

        Livewire::test(\App\Filament\Resources\ConnectorInstanceResource\Pages\EditConnectorInstance::class, ['record' => $connector->id])
            ->assertFormSet([
                'pelican_application_token' => 'ptla_admin',
                'pelican_client_token' => 'ptlc_user',
            ]);
    }

    public function test_discover_servers_action_lists_real_servers_and_marks_ok(): void
    {
        // fixture-panel stands in for Pelican here (moved out to
        // GamingHubProject/BasicConnectors) — implements
        // SupportsServerDiscovery, same as discoverServers now requires.
        $this->installFixtureConnectorPackage();
        InstalledPackage::factory()->create(['slug' => 'fixture-panel', 'status' => 'enabled']);

        $connector = ConnectorInstance::factory()->create([
            'type' => 'fixture-panel',
            'base_url' => 'https://panel.test',
            'credentials' => ['servers' => [['identifier' => 'd3aac351', 'name' => 'EU-1 Palworld']]],
            'status' => 'untested',
        ]);

        Livewire::test(ListConnectorInstances::class)
            ->callTableAction('discoverServers', $connector)
            ->assertSuccessful();

        $this->assertSame('ok', $connector->fresh()->status);
        $this->assertSame(
            [['identifier' => 'd3aac351', 'name' => 'EU-1 Palworld']],
            $connector->fresh()->discovered_servers
        );
    }

    public function test_discover_servers_action_is_hidden_for_a_connector_type_without_discovery_support(): void
    {
        $connector = ConnectorInstance::factory()->create([
            'type' => 'rest',
            'base_url' => 'http://palworld:8212',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
        ]);

        Livewire::test(ListConnectorInstances::class)
            ->assertTableActionHidden('discoverServers', $connector);
    }

    public function test_discover_servers_action_is_hidden_when_the_owning_extension_is_not_installed(): void
    {
        // type=pelican with nothing actually registered for it — the
        // extension simply isn't installed. ConnectorRegistry::get()
        // throws for an unregistered type; the action must hide rather
        // than surface that as a broken button.
        $connector = ConnectorInstance::factory()->create([
            'type' => 'pelican',
            'base_url' => 'https://panel.test',
            'credentials' => ['application_token' => 'ptla_admin'],
        ]);

        Livewire::test(ListConnectorInstances::class)
            ->assertTableActionHidden('discoverServers', $connector);
    }

    public function test_discovered_servers_are_shown_on_the_edit_form(): void
    {
        $connector = ConnectorInstance::create([
            'name' => 'Our Pelican',
            'type' => 'pelican',
            'base_url' => 'https://panel.test',
            'credentials' => ['application_token' => 'ptla_admin'],
            'status' => 'ok',
            'discovered_servers' => [
                ['identifier' => 'd3aac351', 'name' => 'EU-1 Palworld'],
            ],
        ]);

        Livewire::test(\App\Filament\Resources\ConnectorInstanceResource\Pages\EditConnectorInstance::class, ['record' => $connector->id])
            ->assertFormSet([
                'discovered_servers_display' => ['d3aac351' => 'EU-1 Palworld'],
            ]);
    }

    public function test_discover_servers_marks_error_on_failure(): void
    {
        $this->installFixtureConnectorPackage();
        InstalledPackage::factory()->create(['slug' => 'fixture-panel', 'status' => 'enabled']);

        $connector = ConnectorInstance::factory()->create([
            'type' => 'fixture-panel',
            'base_url' => 'https://panel.test',
            'credentials' => ['discovery_fail' => true],
            'status' => 'untested',
        ]);

        Livewire::test(ListConnectorInstances::class)
            ->callTableAction('discoverServers', $connector)
            ->assertSuccessful();

        $this->assertSame('error', $connector->fresh()->status);
    }

    public function test_test_connection_action_marks_ok_on_success(): void
    {
        $connector = ConnectorInstance::factory()->create([
            'type' => 'rest',
            'base_url' => 'http://palworld:8212',
            'test_endpoint' => '/v1/api/info',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
            'status' => 'untested',
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['version' => '1.0', 'servername' => 'My Server']));
        $this->app->instance(HttpRequestContract::class, $fake);

        Livewire::test(ListConnectorInstances::class)
            ->callTableAction('testConnection', $connector)
            ->assertSuccessful();

        $this->assertSame('ok', $connector->fresh()->status);
        $this->assertSame('http://palworld:8212/v1/api/info', $fake->lastUrl());
    }

    public function test_test_connection_action_marks_error_on_failure(): void
    {
        $connector = ConnectorInstance::factory()->create([
            'type' => 'rest',
            'base_url' => 'http://palworld:8212',
            'credentials' => [],
            'status' => 'untested',
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(401, 'Unauthorized');
        $this->app->instance(HttpRequestContract::class, $fake);

        Livewire::test(ListConnectorInstances::class)
            ->callTableAction('testConnection', $connector)
            ->assertSuccessful();

        $this->assertSame('error', $connector->fresh()->status);
    }

    // Regression coverage for both actions also being reachable from the
    // instance's own edit page, not just the list table's row actions —
    // they went missing there at one point without any table-level test
    // catching it, since the table and the edit page are separate Livewire
    // components.

    public function test_discover_servers_action_is_available_on_the_edit_page_and_lists_servers(): void
    {
        $this->installFixtureConnectorPackage();
        InstalledPackage::factory()->create(['slug' => 'fixture-panel', 'status' => 'enabled']);

        $connector = ConnectorInstance::factory()->create([
            'type' => 'fixture-panel',
            'base_url' => 'https://panel.test',
            'credentials' => ['servers' => [['identifier' => 'd3aac351', 'name' => 'EU-1 Palworld']]],
            'status' => 'untested',
        ]);

        Livewire::test(\App\Filament\Resources\ConnectorInstanceResource\Pages\EditConnectorInstance::class, ['record' => $connector->id])
            ->assertActionExists('discoverServers')
            ->callAction('discoverServers')
            ->assertSuccessful();

        $this->assertSame('ok', $connector->fresh()->status);
        $this->assertSame(
            [['identifier' => 'd3aac351', 'name' => 'EU-1 Palworld']],
            $connector->fresh()->discovered_servers
        );
    }

    public function test_test_connection_action_is_available_on_the_edit_page(): void
    {
        $connector = ConnectorInstance::factory()->create([
            'type' => 'rest',
            'base_url' => 'http://palworld:8212',
            'test_endpoint' => '/v1/api/info',
            'credentials' => ['username' => 'admin', 'password' => 'secret'],
            'status' => 'untested',
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode(['version' => '1.0', 'servername' => 'My Server']));
        $this->app->instance(HttpRequestContract::class, $fake);

        Livewire::test(\App\Filament\Resources\ConnectorInstanceResource\Pages\EditConnectorInstance::class, ['record' => $connector->id])
            ->assertActionExists('testConnection')
            ->callAction('testConnection')
            ->assertSuccessful();

        $this->assertSame('ok', $connector->fresh()->status);
    }
}
