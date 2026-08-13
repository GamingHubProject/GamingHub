<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\GameResource\Pages\CreateGame;
use App\Filament\Resources\GameResource\Pages\EditGame;
use App\Filament\Resources\GameResource\Pages\ListGames;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GameResourceTest extends TestCase
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

    public function test_can_list_games(): void
    {
        Game::factory()->count(3)->create();

        Livewire::test(ListGames::class)
            ->assertSuccessful();
    }

    public function test_can_create_game(): void
    {
        Livewire::test(CreateGame::class)
            ->fillForm([
                'name' => 'Palworld',
                'slug' => 'palworld',
                'status' => 'enabled',
                'has_servers' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('games', [
            'name' => 'Palworld',
            'slug' => 'palworld',
        ]);
    }

    public function test_can_edit_game(): void
    {
        $game = Game::factory()->create(['name' => 'Old Name']);

        Livewire::test(EditGame::class, ['record' => $game->getRouteKey()])
            ->fillForm(['name' => 'New Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('games', ['id' => $game->id, 'name' => 'New Name']);
    }

    public function test_can_delete_game(): void
    {
        $game = Game::factory()->create();

        $game->delete();

        $this->assertDatabaseMissing('games', ['id' => $game->id]);
    }

    public function test_game_may_have_zero_servers(): void
    {
        $game = Game::factory()->create(['has_servers' => false]);

        $this->assertSame(0, $game->servers()->count());
    }
}
