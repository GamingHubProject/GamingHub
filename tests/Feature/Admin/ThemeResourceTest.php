<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ThemeResource\Pages\CreateTheme;
use App\Filament\Resources\ThemeResource\Pages\ListThemes;
use GamingHub\Core\Models\Game;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ThemeResourceTest extends TestCase
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

    public function test_can_list_themes(): void
    {
        Theme::create(['name' => 'Default', 'level' => Theme::LEVEL_PLATFORM, 'tokens' => []]);

        Livewire::test(ListThemes::class)->assertSuccessful();
    }

    public function test_can_create_platform_theme(): void
    {
        Livewire::test(CreateTheme::class)
            ->fillForm([
                'name' => 'Platform Default',
                'level' => Theme::LEVEL_PLATFORM,
                'tokens' => ['color-primary' => '#4f46e5'],
                'is_default' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('themes', ['name' => 'Platform Default', 'level' => Theme::LEVEL_PLATFORM]);
    }

    public function test_can_create_game_theme_override(): void
    {
        $game = Game::factory()->create();

        Livewire::test(CreateTheme::class)
            ->fillForm([
                'name' => 'Palworld Theme',
                'level' => Theme::LEVEL_GAME,
                'game_id' => $game->id,
                'tokens' => ['color-primary' => '#16a34a'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('themes', ['name' => 'Palworld Theme', 'game_id' => $game->id]);
    }
}
