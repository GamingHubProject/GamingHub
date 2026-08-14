<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ConfigurationPresetResource\Pages\CreateConfigurationPreset;
use App\Filament\Resources\ConfigurationPresetResource\Pages\ListConfigurationPresets;
use App\Models\ConfigurationPreset;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConfigurationPresetResourceTest extends TestCase
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

    public function test_can_list_presets(): void
    {
        ConfigurationPreset::factory()->count(2)->create();

        Livewire::test(ListConfigurationPresets::class)->assertSuccessful();
    }

    public function test_can_create_preset_with_values(): void
    {
        $game = Game::factory()->create();

        Livewire::test(CreateConfigurationPreset::class)
            ->fillForm([
                'game_id' => $game->id,
                'name' => 'hardcore',
                'values' => ['ExpRate' => '0.5'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $preset = ConfigurationPreset::where('game_id', $game->id)->where('name', 'hardcore')->first();
        $this->assertNotNull($preset);
        $this->assertSame('0.5', $preset->values['ExpRate']);
    }

    public function test_preset_name_is_unique_per_game(): void
    {
        $game = Game::factory()->create();
        ConfigurationPreset::factory()->create(['game_id' => $game->id, 'name' => 'hardcore']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ConfigurationPreset::factory()->create(['game_id' => $game->id, 'name' => 'hardcore']);
    }
}
