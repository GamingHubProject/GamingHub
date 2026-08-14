<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\CapabilityBindingResource\Pages\CreateCapabilityBinding;
use App\Filament\Resources\CapabilityBindingResource\Pages\ListCapabilityBindings;
use App\Models\CapabilityBinding;
use GamingHub\Core\Models\Server;
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
                'value' => ['online' => true, 'players' => 5],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('capability_bindings', [
            'capability' => 'server-status',
            'subject_type' => 'server',
            'subject_id' => $server->id,
        ]);
    }
}
