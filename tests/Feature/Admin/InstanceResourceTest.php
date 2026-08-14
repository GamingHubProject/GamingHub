<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\InstanceResource\Pages\CreateInstance;
use App\Filament\Resources\InstanceResource\Pages\ListInstances;
use App\Models\ConfigurationPreset;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Instance;
use GamingHub\Core\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstanceResourceTest extends TestCase
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

    public function test_can_list_instances(): void
    {
        Instance::factory()->count(2)->create();

        Livewire::test(ListInstances::class)->assertSuccessful();
    }

    public function test_can_create_instance_with_schema_driven_configuration(): void
    {
        $game = Game::factory()->create([
            'configuration_schema' => [
                ['key' => 'ExpRate', 'type' => 'decimal', 'min' => 0.1, 'max' => 10, 'default' => 1],
                ['key' => 'PvPEnabled', 'type' => 'boolean', 'default' => false],
            ],
        ]);
        $server = Server::factory()->create(['game_id' => $game->id]);

        Livewire::test(CreateInstance::class)
            ->fillForm([
                'server_id' => $server->id,
                'name' => 'Main',
                'configuration' => [
                    'ExpRate' => '2.5',
                    'PvPEnabled' => true,
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $instance = Instance::where('name', 'Main')->first();
        $this->assertNotNull($instance);
        $this->assertSame('2.5', $instance->configuration['ExpRate']);
        $this->assertTrue($instance->configuration['PvPEnabled']);
    }

    public function test_applying_a_preset_copies_its_values_into_configuration(): void
    {
        $game = Game::factory()->create([
            'configuration_schema' => [
                ['key' => 'ExpRate', 'type' => 'decimal', 'default' => 1],
            ],
        ]);
        $server = Server::factory()->create(['game_id' => $game->id]);
        $preset = ConfigurationPreset::factory()->create([
            'game_id' => $game->id,
            'name' => 'hardcore',
            'values' => ['ExpRate' => 0.5],
        ]);

        Livewire::test(CreateInstance::class)
            ->fillForm(['server_id' => $server->id])
            ->set('data.apply_preset', (string) $preset->id)
            ->assertSet('data.configuration.ExpRate', 0.5);
    }

    public function test_instance_without_matching_game_schema_shows_no_dynamic_fields(): void
    {
        $server = Server::factory()->create();

        Livewire::test(CreateInstance::class)
            ->fillForm([
                'server_id' => $server->id,
                'name' => 'Bare Instance',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('instances', ['name' => 'Bare Instance']);
    }
}
