<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\GameExtensionResource\Pages\CreateGameExtension;
use App\Filament\Resources\GameExtensionResource\Pages\ListGameExtensions;
use App\Models\GameExtension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GameExtensionResourceTest extends TestCase
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

    public function test_can_list_extensions(): void
    {
        GameExtension::factory()->count(2)->create();

        Livewire::test(ListGameExtensions::class)->assertSuccessful();
    }

    public function test_can_register_an_extension_unbound_to_a_game(): void
    {
        Livewire::test(CreateGameExtension::class)
            ->fillForm([
                'slug' => 'palworld-integration',
                'name' => 'Palworld Integration',
                'version' => '0.1.000',
                'status' => 'disabled',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('game_extensions', [
            'slug' => 'palworld-integration',
            'game_id' => null,
        ]);
    }

    public function test_can_toggle_status(): void
    {
        $extension = GameExtension::factory()->create(['status' => 'disabled']);

        $extension->update(['status' => 'enabled']);

        $this->assertSame('enabled', $extension->fresh()->status);
    }
}
