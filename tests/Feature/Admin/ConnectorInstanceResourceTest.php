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
                'status' => 'untested',
                'credentials' => ['token' => 'ptlc_abc'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('connector_instances', ['name' => 'Our Pelican', 'type' => 'pelican']);
    }

    public function test_discover_servers_action_lists_real_servers(): void
    {
        $connector = ConnectorInstance::factory()->create([
            'type' => 'pelican',
            'base_url' => 'https://panel.test',
            'credentials' => ['token' => 'ptlc_abc'],
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
    }
}
