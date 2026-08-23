<?php

namespace Tests\Feature\Api;

use App\Models\DashboardPage;
use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_the_authenticated_users_own_pages(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        DashboardPage::create(['user_id' => $user->id, 'title' => 'My Dashboard']);
        DashboardPage::create(['user_id' => $other->id, 'title' => 'Someone Else']);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard/pages');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'My Dashboard');
    }

    public function test_store_creates_a_page_owned_by_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/dashboard/pages', ['title' => 'New Page']);

        $response->assertCreated();
        $this->assertDatabaseHas('dashboard_pages', ['title' => 'New Page', 'user_id' => $user->id]);
    }

    public function test_store_response_includes_an_empty_widgets_array_not_a_missing_key(): void
    {
        // whenLoaded('widgets') in DashboardPageResource returns a
        // MissingValue (key omitted entirely) unless the relation was
        // explicitly loaded — a brand new page has no widgets to eager
        // load implicitly, so this needs the resource to see an empty,
        // already-loaded collection rather than an unloaded relation.
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/dashboard/pages', ['title' => 'New Page']);

        $response->assertCreated();
        $response->assertJsonPath('data.widgets', []);
    }

    public function test_dashboard_endpoints_401_for_a_guest(): void
    {
        $this->getJson('/api/v1/dashboard/pages')->assertUnauthorized();
        $this->postJson('/api/v1/dashboard/pages', ['title' => 'x'])->assertUnauthorized();
    }

    public function test_widget_store_requires_a_page_owned_by_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherPage = DashboardPage::create(['user_id' => $other->id, 'title' => 'Not Mine']);

        $response = $this->actingAs($user)->postJson('/api/v1/dashboard/widgets', [
            'dashboard_page_id' => $otherPage->id,
            'widget_type' => 'server-status',
        ]);

        $response->assertUnprocessable();
    }

    public function test_widget_store_succeeds_for_a_page_the_user_owns(): void
    {
        $user = User::factory()->create();
        $page = DashboardPage::create(['user_id' => $user->id, 'title' => 'Mine']);

        $response = $this->actingAs($user)->postJson('/api/v1/dashboard/widgets', [
            'dashboard_page_id' => $page->id,
            'widget_type' => 'server-status',
            'config' => ['server_id' => 1],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('dashboard_widgets', ['dashboard_page_id' => $page->id, 'widget_type' => 'server-status']);
    }

    public function test_widget_update_404s_when_the_widget_belongs_to_another_users_page(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherPage = DashboardPage::create(['user_id' => $other->id, 'title' => 'Not Mine']);
        $widget = DashboardWidget::create([
            'dashboard_page_id' => $otherPage->id,
            'widget_type' => 'server-status',
            'order' => 0,
        ]);

        $response = $this->actingAs($user)->patchJson("/api/v1/dashboard/widgets/{$widget->id}", [
            'widget_type' => 'hijacked',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('dashboard_widgets', ['id' => $widget->id, 'widget_type' => 'server-status']);
    }

    public function test_widget_update_succeeds_for_the_owning_user(): void
    {
        $user = User::factory()->create();
        $page = DashboardPage::create(['user_id' => $user->id, 'title' => 'Mine']);
        $widget = DashboardWidget::create([
            'dashboard_page_id' => $page->id,
            'widget_type' => 'server-status',
            'order' => 0,
        ]);

        $response = $this->actingAs($user)->patchJson("/api/v1/dashboard/widgets/{$widget->id}", [
            'order' => 5,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('dashboard_widgets', ['id' => $widget->id, 'order' => 5]);
    }
}
