<?php

namespace Tests\Feature\Api;

use App\Models\GroupWidgetTemplate;
use App\Models\PageLayout;
use App\Models\PageLayoutWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GroupWidgetTemplateApiTest extends TestCase
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

    private function groupWithChildren(): PageLayoutWidget
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $group = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'group', 'width' => 8, 'height' => 4]);
        PageLayoutWidget::create([
            'page_layout_id' => $layout->id,
            'group_widget_id' => $group->id,
            'widget_type' => 'server-name',
            'config' => ['font_size' => 24, 'text_color' => '#ffffff', 'server_id' => null],
            'position_x' => 0,
            'position_y' => 0,
            'width' => 4,
            'height' => 1,
        ]);
        PageLayoutWidget::create([
            'page_layout_id' => $layout->id,
            'group_widget_id' => $group->id,
            'widget_type' => 'picture',
            'position_x' => 0,
            'position_y' => 1,
            'width' => 8,
            'height' => 2,
        ]);

        return $group;
    }

    // --- store ---

    public function test_store_requires_the_admin_role(): void
    {
        $group = $this->groupWithChildren();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/group-widget-templates', ['name' => 'Hero', 'group_widget_id' => $group->id])
            ->assertForbidden();
    }

    public function test_store_rejects_a_group_widget_id_that_is_not_a_group(): void
    {
        $layout = PageLayout::create(['subject_type' => 'home', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $notAGroup = PageLayoutWidget::create(['page_layout_id' => $layout->id, 'widget_type' => 'picture']);

        $response = $this->actingAs($this->admin())->postJson('/api/v1/group-widget-templates', [
            'name' => 'Hero',
            'group_widget_id' => $notAGroup->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_captures_the_groups_current_children_into_the_snapshot(): void
    {
        $group = $this->groupWithChildren();

        $response = $this->actingAs($this->admin())->postJson('/api/v1/group-widget-templates', [
            'name' => 'Hero banner',
            'group_widget_id' => $group->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Hero banner');

        $template = GroupWidgetTemplate::first();
        $this->assertSame(8, $template->snapshot['width']);
        $this->assertSame(4, $template->snapshot['height']);
        $this->assertCount(2, $template->snapshot['children']);
        $this->assertSame('server-name', $template->snapshot['children'][0]['widget_type']);
        $this->assertSame('picture', $template->snapshot['children'][1]['widget_type']);
    }

    public function test_store_snapshot_is_independent_of_later_edits_to_the_source_group(): void
    {
        $group = $this->groupWithChildren();

        $this->actingAs($this->admin())->postJson('/api/v1/group-widget-templates', [
            'name' => 'Hero banner',
            'group_widget_id' => $group->id,
        ])->assertCreated();

        $template = GroupWidgetTemplate::first();

        // Mutate the source group after the snapshot was taken.
        $group->children()->first()->update(['config' => ['font_size' => 99, 'text_color' => '#000000', 'server_id' => null]]);
        $group->children()->latest('id')->first()->delete();

        $template->refresh();
        $this->assertCount(2, $template->snapshot['children']);
        $this->assertSame(24, $template->snapshot['children'][0]['config']['font_size']);
    }

    // --- index ---

    public function test_index_requires_the_admin_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/group-widget-templates')->assertForbidden();
    }

    public function test_index_lists_templates_by_name(): void
    {
        GroupWidgetTemplate::create(['name' => 'Zebra', 'snapshot' => ['width' => 4, 'height' => 2, 'children' => []]]);
        GroupWidgetTemplate::create(['name' => 'Apple', 'snapshot' => ['width' => 4, 'height' => 2, 'children' => []]]);

        $response = $this->actingAs($this->admin())->getJson('/api/v1/group-widget-templates');

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Apple');
        $response->assertJsonPath('data.1.name', 'Zebra');
    }

    // --- destroy ---

    public function test_destroy_requires_the_admin_role(): void
    {
        $template = GroupWidgetTemplate::create(['name' => 'Hero', 'snapshot' => ['width' => 4, 'height' => 2, 'children' => []]]);
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson("/api/v1/group-widget-templates/{$template->id}")->assertForbidden();
    }

    public function test_destroy_removes_the_template(): void
    {
        $template = GroupWidgetTemplate::create(['name' => 'Hero', 'snapshot' => ['width' => 4, 'height' => 2, 'children' => []]]);

        $response = $this->actingAs($this->admin())->deleteJson("/api/v1/group-widget-templates/{$template->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('group_widget_templates', ['id' => $template->id]);
    }

    // --- place ---

    public function test_place_requires_the_admin_role(): void
    {
        $template = GroupWidgetTemplate::create(['name' => 'Hero', 'snapshot' => ['width' => 4, 'height' => 2, 'children' => []]]);
        $layout = PageLayout::create(['subject_type' => 'games-list', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/v1/page-layouts/{$layout->id}/group-widgets/from-template/{$template->id}")
            ->assertForbidden();
    }

    public function test_place_creates_an_independent_group_and_children_on_the_target_layout(): void
    {
        $sourceGroup = $this->groupWithChildren();
        $this->actingAs($this->admin())->postJson('/api/v1/group-widget-templates', [
            'name' => 'Hero banner',
            'group_widget_id' => $sourceGroup->id,
        ])->assertCreated();
        $template = GroupWidgetTemplate::first();

        $targetLayout = PageLayout::create(['subject_type' => 'games-list', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);

        $response = $this->actingAs($this->admin())->postJson(
            "/api/v1/page-layouts/{$targetLayout->id}/group-widgets/from-template/{$template->id}"
        );

        $response->assertOk();
        $created = $response->json('data');
        $this->assertCount(3, $created); // 1 group + 2 children

        $newGroup = collect($created)->firstWhere('widget_type', 'group');
        $this->assertNotNull($newGroup);
        $this->assertNotSame($sourceGroup->id, $newGroup['id']);
        $this->assertSame($targetLayout->id, $newGroup['page_layout_id']);
        $this->assertSame(8, $newGroup['width']);

        $newChildren = collect($created)->where('widget_type', '!=', 'group');
        $this->assertCount(2, $newChildren);
        $newChildren->each(fn ($child) => $this->assertSame($newGroup['id'], $child['group_widget_id']));

        // Independent rows, not references to the source group's children —
        // source (1 group + 2 children) plus target (1 group + 2 children).
        $this->assertDatabaseCount('page_layout_widgets', 6);
    }

    public function test_place_result_is_unaffected_by_later_edits_to_the_source_group(): void
    {
        $sourceGroup = $this->groupWithChildren();
        $this->actingAs($this->admin())->postJson('/api/v1/group-widget-templates', [
            'name' => 'Hero banner',
            'group_widget_id' => $sourceGroup->id,
        ])->assertCreated();
        $template = GroupWidgetTemplate::first();

        $targetLayout = PageLayout::create(['subject_type' => 'games-list', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        $this->actingAs($this->admin())->postJson(
            "/api/v1/page-layouts/{$targetLayout->id}/group-widgets/from-template/{$template->id}"
        )->assertOk();

        $placedNameWidget = PageLayoutWidget::where('page_layout_id', $targetLayout->id)->where('widget_type', 'server-name')->first();

        // Edit the *original* group's child after placement.
        $sourceGroup->children()->where('widget_type', 'server-name')->first()->update([
            'config' => ['font_size' => 999, 'text_color' => '#123456', 'server_id' => null],
        ]);

        $this->assertSame(24, $placedNameWidget->fresh()->config['font_size']);
    }

    public function test_place_stacks_the_new_group_below_existing_top_level_widgets_on_the_target_layout(): void
    {
        $sourceGroup = $this->groupWithChildren();
        $this->actingAs($this->admin())->postJson('/api/v1/group-widget-templates', [
            'name' => 'Hero banner',
            'group_widget_id' => $sourceGroup->id,
        ])->assertCreated();
        $template = GroupWidgetTemplate::first();

        $targetLayout = PageLayout::create(['subject_type' => 'games-list', 'subject_id' => PageLayout::SINGLETON_SUBJECT_ID]);
        PageLayoutWidget::create(['page_layout_id' => $targetLayout->id, 'widget_type' => 'picture', 'position_x' => 0, 'position_y' => 0, 'width' => 12, 'height' => 3]);

        $response = $this->actingAs($this->admin())->postJson(
            "/api/v1/page-layouts/{$targetLayout->id}/group-widgets/from-template/{$template->id}"
        );

        $newGroup = collect($response->json('data'))->firstWhere('widget_type', 'group');
        $this->assertSame(3, $newGroup['position_y']);
    }
}
