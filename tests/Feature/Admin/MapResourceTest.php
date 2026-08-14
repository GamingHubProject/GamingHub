<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\MapResource\Pages\CreateMap;
use App\Filament\Resources\MapResource\Pages\ListMaps;
use GamingHub\Core\Models\Game;
use App\Models\Map;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MapResourceTest extends TestCase
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

    public function test_can_list_maps(): void
    {
        Map::factory()->count(2)->create();

        Livewire::test(ListMaps::class)->assertSuccessful();
    }

    public function test_can_create_map(): void
    {
        $game = Game::factory()->create();

        Livewire::test(CreateMap::class)
            ->fillForm([
                'game_id' => $game->id,
                'name' => 'Grind Route',
                'slug' => 'grind-route',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('maps', ['slug' => 'grind-route', 'game_id' => $game->id]);
    }

    public function test_map_belongs_to_game_without_requiring_a_server(): void
    {
        $game = Game::factory()->create(['has_servers' => false]);
        $map = Map::factory()->create(['game_id' => $game->id]);

        $this->assertTrue($map->game->is($game));
        $this->assertSame(0, $game->servers()->count());
    }
}
