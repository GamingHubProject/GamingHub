<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\PlatformOverviewWidget;
use App\Filament\Widgets\ServersByStatusWidget;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
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

    public function test_dashboard_loads_with_widgets(): void
    {
        $response = $this->get('/admin/system');

        $response->assertStatus(200);
    }

    public function test_platform_overview_widget_counts_are_correct(): void
    {
        Game::factory()->count(2)->create(['status' => 'enabled']);
        Server::factory()->count(3)->create(['status' => 'online']);

        Livewire::test(PlatformOverviewWidget::class)->assertSuccessful();
    }

    public function test_servers_by_status_widget_renders(): void
    {
        Server::factory()->create(['status' => 'online']);
        Server::factory()->create(['status' => 'offline']);

        Livewire::test(ServersByStatusWidget::class)->assertSuccessful();
    }
}
