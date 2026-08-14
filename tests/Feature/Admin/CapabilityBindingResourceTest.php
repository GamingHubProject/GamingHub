<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\CapabilityBindingResource\Pages\CreateCapabilityBinding;
use App\Filament\Resources\CapabilityBindingResource\Pages\EditCapabilityBinding;
use App\Filament\Resources\CapabilityBindingResource\Pages\ListCapabilityBindings;
use GamingHub\Core\Models\CapabilityBinding;
use GamingHub\Core\Models\Server;
use App\Models\ConnectorInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CapabilityBindingResourceTest extends TestCase
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

    public function test_can_list_bindings(): void
    {
        $server = Server::factory()->create();

        CapabilityBinding::create([
            'capability' => 'server-status',
            'subject_type' => 'server',
            'subject_id' => $server->id,
            'provider' => 'manual',
            'value' => ['online' => true],
        ]);

        Livewire::test(ListCapabilityBindings::class)->assertSuccessful();
    }

    public function test_can_create_binding_for_a_server(): void
    {
        $server = Server::factory()->create();

        Livewire::test(CreateCapabilityBinding::class)
            ->fillForm([
                'capability' => 'server-status',
                'subject_type' => 'server',
                'subject_id' => $server->id,
                'provider' => 'manual',
                'enabled' => true,
                'manual_value' => ['online' => true, 'players' => 5],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('capability_bindings', [
            'capability' => 'server-status',
            'subject_type' => 'server',
            'subject_id' => $server->id,
        ]);

        $binding = CapabilityBinding::where('subject_id', $server->id)->firstOrFail();
        $this->assertSame(['online' => '1', 'players' => '5'], $binding->value);
    }

    public function test_can_create_connector_backed_binding(): void
    {
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'rest']);

        Livewire::test(CreateCapabilityBinding::class)
            ->fillForm([
                'capability' => 'server-status',
                'subject_type' => 'server',
                'subject_id' => $server->id,
                'provider' => 'connector',
                'enabled' => true,
                'connector_instance_id' => $connector->id,
                'connector_call' => ['endpoint' => '/v1/api/metrics'],
                'connector_normalizer' => 'palworld-server-status',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $binding = CapabilityBinding::where('subject_id', $server->id)->firstOrFail();
        $this->assertSame('connector', $binding->provider);
        $this->assertSame($connector->id, $binding->value['connector_instance_id']);
        $this->assertSame('/v1/api/metrics', $binding->value['call']['endpoint']);
        $this->assertSame('palworld-server-status', $binding->value['normalizer']);
    }

    public function test_can_edit_a_connector_backed_binding(): void
    {
        $server = Server::factory()->create();
        $connector = ConnectorInstance::factory()->create(['type' => 'pelican']);

        $binding = CapabilityBinding::create([
            'capability' => 'server-status',
            'subject_type' => 'server',
            'subject_id' => $server->id,
            'provider' => 'connector',
            'value' => [
                'connector_instance_id' => $connector->id,
                'call' => ['server_identifier' => 'abc123'],
                'normalizer' => 'pelican-server-status',
            ],
        ]);

        Livewire::test(EditCapabilityBinding::class, ['record' => $binding->id])
            ->assertFormSet([
                'connector_instance_id' => $connector->id,
                'connector_normalizer' => 'pelican-server-status',
            ])
            ->fillForm(['enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($binding->fresh()->enabled);
        $this->assertSame('abc123', $binding->fresh()->value['call']['server_identifier']);
    }
}
