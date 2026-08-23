<?php

namespace Tests\Feature\Api;

use App\Models\ServerLayout;
use App\Models\ServerLayoutWidget;
use App\Models\User;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServerLayoutApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        return $admin;
    }

    public function test_show_auto_creates_an_empty_layout_for_a_server_with_none_yet(): void
    {
        $server = Server::factory()->create();

        $response = $this->getJson("/api/v1/servers/{$server->id}/layout");

        $response->assertOk();
        $response->assertJsonPath('data.server_id', $server->id);
        $response->assertJsonPath('data.widgets', []);
        $this->assertDatabaseHas('server_layouts', ['server_id' => $server->id]);
    }

    public function test_show_is_public_no_auth_required(): void
    {
        $server = Server::factory()->create();

        $this->getJson("/api/v1/servers/{$server->id}/layout")->assertOk();
    }

    public function test_show_returns_existing_widgets(): void
    {
        $server = Server::factory()->create();
        $layout = ServerLayout::create(['server_id' => $server->id]);
        ServerLayoutWidget::create([
            'server_layout_id' => $layout->id,
            'widget_type' => 'server-status',
            'position_x' => 0,
            'position_y' => 0,
            'width' => 3,
            'height' => 2,
        ]);

        $response = $this->getJson("/api/v1/servers/{$server->id}/layout");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.widgets');
        $response->assertJsonPath('data.widgets.0.widget_type', 'server-status');
    }

    public function test_show_does_not_duplicate_the_layout_row_on_repeat_requests(): void
    {
        $server = Server::factory()->create();

        $this->getJson("/api/v1/servers/{$server->id}/layout")->assertOk();
        $this->getJson("/api/v1/servers/{$server->id}/layout")->assertOk();

        $this->assertSame(1, ServerLayout::where('server_id', $server->id)->count());
    }

    public function test_store_requires_authentication(): void
    {
        $server = Server::factory()->create();

        $this->postJson("/api/v1/servers/{$server->id}/layout/widgets", ['widget_type' => 'server-status'])
            ->assertUnauthorized();
    }

    public function test_store_requires_the_admin_role(): void
    {
        $server = Server::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/v1/servers/{$server->id}/layout/widgets", ['widget_type' => 'server-status'])
            ->assertForbidden();
    }

    public function test_store_creates_a_widget_and_the_layout_row_if_missing(): void
    {
        $server = Server::factory()->create();

        $response = $this->actingAs($this->admin())->postJson("/api/v1/servers/{$server->id}/layout/widgets", [
            'widget_type' => 'server-banner',
            'position_x' => 0,
            'position_y' => 0,
            'width' => 12,
            'height' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.widget_type', 'server-banner');
        $response->assertJsonPath('data.width', 12);
        $this->assertDatabaseHas('server_layouts', ['server_id' => $server->id]);
        $this->assertDatabaseHas('server_layout_widgets', ['widget_type' => 'server-banner', 'width' => 12]);
    }

    public function test_store_falls_back_to_default_position_and_size_when_omitted(): void
    {
        $server = Server::factory()->create();

        $response = $this->actingAs($this->admin())->postJson("/api/v1/servers/{$server->id}/layout/widgets", [
            'widget_type' => 'server-status',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.position_x', 0);
        $response->assertJsonPath('data.position_y', 0);
        $response->assertJsonPath('data.width', 6);
        $response->assertJsonPath('data.height', 4);
    }

    public function test_update_requires_the_admin_role(): void
    {
        $server = Server::factory()->create();
        $layout = ServerLayout::create(['server_id' => $server->id]);
        $widget = ServerLayoutWidget::create([
            'server_layout_id' => $layout->id,
            'widget_type' => 'server-status',
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson("/api/v1/server-layout-widgets/{$widget->id}", ['width' => 3])
            ->assertForbidden();
    }

    public function test_update_persists_a_new_position_and_size(): void
    {
        $server = Server::factory()->create();
        $layout = ServerLayout::create(['server_id' => $server->id]);
        $widget = ServerLayoutWidget::create([
            'server_layout_id' => $layout->id,
            'widget_type' => 'server-metrics',
            'position_x' => 0,
            'position_y' => 0,
            'width' => 6,
            'height' => 4,
        ]);

        $response = $this->actingAs($this->admin())->patchJson("/api/v1/server-layout-widgets/{$widget->id}", [
            'position_x' => 3,
            'position_y' => 4,
            'width' => 4,
            'height' => 3,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('server_layout_widgets', [
            'id' => $widget->id,
            'position_x' => 3,
            'position_y' => 4,
            'width' => 4,
            'height' => 3,
        ]);
    }

    public function test_update_persists_a_config_toggle(): void
    {
        $server = Server::factory()->create();
        $layout = ServerLayout::create(['server_id' => $server->id]);
        $widget = ServerLayoutWidget::create([
            'server_layout_id' => $layout->id,
            'widget_type' => 'server-status',
        ]);

        $response = $this->actingAs($this->admin())->patchJson("/api/v1/server-layout-widgets/{$widget->id}", [
            'config' => ['show_node' => true],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.config.show_node', true);
        $this->assertSame(['show_node' => true], $widget->fresh()->config);
    }

    public function test_destroy_requires_the_admin_role(): void
    {
        $server = Server::factory()->create();
        $layout = ServerLayout::create(['server_id' => $server->id]);
        $widget = ServerLayoutWidget::create([
            'server_layout_id' => $layout->id,
            'widget_type' => 'server-allocations',
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson("/api/v1/server-layout-widgets/{$widget->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('server_layout_widgets', ['id' => $widget->id]);
    }

    public function test_destroy_removes_the_widget(): void
    {
        $server = Server::factory()->create();
        $layout = ServerLayout::create(['server_id' => $server->id]);
        $widget = ServerLayoutWidget::create([
            'server_layout_id' => $layout->id,
            'widget_type' => 'server-allocations',
        ]);

        $response = $this->actingAs($this->admin())->deleteJson("/api/v1/server-layout-widgets/{$widget->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('server_layout_widgets', ['id' => $widget->id]);
    }
}
