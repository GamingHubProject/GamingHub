<?php

namespace Tests\Feature\Admin;

use App\Connectors\HttpRequestContract;
use App\Filament\Resources\ConnectorInstanceResource\Pages\CreateConnectorInstance;
use App\Filament\Resources\ConnectorInstanceResource\Pages\ListConnectorInstances;
use App\Models\ConnectorInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Unit\Connectors\Support\FakeHttpRequester;

class ConnectorInstanceResourceTest extends TestCase
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

    public function test_can_create_a_pelican_connector(): void
    {
        Livewire::test(CreateConnectorInstance::class)
            ->fillForm([
                'name' => 'Our Pelican',
                'type' => 'pelican',
                'base_url' => 'https://panel.test',
                'pelican_token' => 'ptlc_abc',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('connector_instances', ['name' => 'Our Pelican', 'type' => 'pelican']);
        $connector = ConnectorInstance::where('name', 'Our Pelican')->firstOrFail();
        $this->assertSame(['token' => 'ptlc_abc'], $connector->credentials);
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
            'credentials' => ['token' => 'ptlc_abc'],
            'status' => 'untested',
        ]);

        Livewire::test(\App\Filament\Resources\ConnectorInstanceResource\Pages\EditConnectorInstance::class, ['record' => $connector->id])
            ->assertFormSet(['pelican_token' => 'ptlc_abc']);
    }

    public function test_discover_servers_action_lists_real_servers_and_marks_ok(): void
    {
        $connector = ConnectorInstance::factory()->create([
            'type' => 'pelican',
            'base_url' => 'https://panel.test',
            'credentials' => ['token' => 'ptlc_abc'],
            'status' => 'untested',
        ]);

        $fake = new FakeHttpRequester;
        $fake->willReturn(200, json_encode([
            'data' => [
                ['attributes' => ['identifier' => 'd3aac351', 'name' => 'EU-1 Palworld']],
            ],
        ]));
        $this->app->instance(HttpRequestContract::class, $fake);

        Livewire::test(ListConnectorInstances::class)
            ->callTableAction('discoverServers', $connector)
            ->assertSuccessful();

        $this->assertSame('ok', $connector->fresh()->status);
    }

    public function test_discover_servers_marks_error_on_failure(): void
    {
        $connector = ConnectorInstance::factory()->create([
            'type' => 'pelican',
            'base_url' => 'https://panel.test',
            'credentials' => [],
            'status' => 'untested',
        ]);

        $this->app->instance(HttpRequestContract::class, new FakeHttpRequester);

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
}
