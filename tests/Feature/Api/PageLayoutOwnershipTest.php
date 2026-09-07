<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\GroupWidgetTemplate;
use App\Models\PageLayout;
use App\Models\PageLayoutWidget;
use App\Models\User;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The page_layouts write rule, from both directions.
 *
 * Until this release every layout was a site page and every write endpoint
 * asked for the Admin role inline. Profiles make a layout something its
 * subject owns, so the question moved onto the layout itself
 * (PageLayout::canBeEditedBy). Two things have to stay true afterwards:
 * an admin still writes every site page exactly as before, and an ordinary
 * user gains access to their own profile and to nothing else — in
 * particular not by handing an endpoint a widget id that belongs to a page
 * they don't own, which is the shape the widget routes make possible.
 */
class PageLayoutOwnershipTest extends TestCase
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

    private function layout(string $subjectType, int $subjectId = PageLayout::SINGLETON_SUBJECT_ID): PageLayout
    {
        return PageLayout::create(['subject_type' => $subjectType, 'subject_id' => $subjectId]);
    }

    private function widgetOn(PageLayout $layout): PageLayoutWidget
    {
        return PageLayoutWidget::create([
            'page_layout_id' => $layout->id,
            'widget_type' => 'server-status',
            'position_x' => 0,
            'position_y' => 0,
            'width' => 6,
            'height' => 4,
        ]);
    }

    /** @return array<string, array{string, bool}> */
    public static function siteSubjects(): array
    {
        return [
            'home' => ['home', true],
            'games list' => ['games-list', true],
            'server' => ['server', false],
            'game' => ['game', false],
        ];
    }

    private function siteLayout(string $subjectType, bool $singleton): PageLayout
    {
        if ($singleton) {
            return $this->layout($subjectType);
        }

        $subjectId = $subjectType === 'server'
            ? Server::factory()->create()->id
            : Game::factory()->create(['status' => 'enabled'])->id;

        return $this->layout($subjectType, $subjectId);
    }

    // --- Admins keep every layout they had before ---

    #[\PHPUnit\Framework\Attributes\DataProvider('siteSubjects')]
    public function test_an_admin_can_still_add_a_widget_to_every_existing_subject_type(string $subjectType, bool $singleton): void
    {
        $layout = $this->siteLayout($subjectType, $singleton);

        $this->actingAs($this->admin())
            ->postJson("/api/v1/page-layouts/{$layout->id}/widgets", ['widget_type' => 'picture'])
            ->assertCreated();

        $this->assertDatabaseHas('page_layout_widgets', ['page_layout_id' => $layout->id, 'widget_type' => 'picture']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('siteSubjects')]
    public function test_an_admin_can_still_move_and_delete_a_widget_on_every_existing_subject_type(string $subjectType, bool $singleton): void
    {
        $layout = $this->siteLayout($subjectType, $singleton);
        $widget = $this->widgetOn($layout);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/v1/page-layout-widgets/{$widget->id}", ['width' => 3])
            ->assertOk();
        $this->assertDatabaseHas('page_layout_widgets', ['id' => $widget->id, 'width' => 3]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/page-layout-widgets/{$widget->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('page_layout_widgets', ['id' => $widget->id]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('siteSubjects')]
    public function test_an_admin_can_still_set_the_font_on_every_existing_subject_type(string $subjectType, bool $singleton): void
    {
        $layout = $this->siteLayout($subjectType, $singleton);
        $font = Asset::factory()->create();

        $this->actingAs($this->admin())
            ->patchJson("/api/v1/page-layouts/{$layout->id}", ['font_asset_id' => $font->id])
            ->assertOk();

        $this->assertDatabaseHas('page_layouts', ['id' => $layout->id, 'font_asset_id' => $font->id]);
    }

    // --- Ordinary users still get nothing on a site page ---

    #[\PHPUnit\Framework\Attributes\DataProvider('siteSubjects')]
    public function test_an_ordinary_user_cannot_write_to_any_site_page(string $subjectType, bool $singleton): void
    {
        $layout = $this->siteLayout($subjectType, $singleton);
        $widget = $this->widgetOn($layout);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/v1/page-layouts/{$layout->id}/widgets", ['widget_type' => 'picture'])
            ->assertForbidden();
        $this->actingAs($user)
            ->patchJson("/api/v1/page-layout-widgets/{$widget->id}", ['width' => 3])
            ->assertForbidden();
        $this->actingAs($user)
            ->deleteJson("/api/v1/page-layout-widgets/{$widget->id}")
            ->assertForbidden();
        $this->actingAs($user)
            ->patchJson("/api/v1/page-layouts/{$layout->id}", ['font_asset_id' => null])
            ->assertForbidden();
    }

    // --- A profile is the one layout its subject owns ---

    public function test_a_user_can_add_move_and_delete_widgets_on_their_own_profile(): void
    {
        $user = User::factory()->create();
        $layout = $this->layout(PageLayout::SUBJECT_USER_PROFILE, $user->id);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/page-layouts/{$layout->id}/widgets", ['widget_type' => 'picture']);
        $response->assertCreated();

        $widgetId = $response->json('data.id');

        $this->actingAs($user)
            ->patchJson("/api/v1/page-layout-widgets/{$widgetId}", ['width' => 4])
            ->assertOk();
        $this->actingAs($user)
            ->deleteJson("/api/v1/page-layout-widgets/{$widgetId}")
            ->assertNoContent();
    }

    public function test_a_user_can_set_the_font_on_their_own_profile(): void
    {
        $user = User::factory()->create();
        $layout = $this->layout(PageLayout::SUBJECT_USER_PROFILE, $user->id);
        $font = Asset::factory()->create();

        $this->actingAs($user)
            ->patchJson("/api/v1/page-layouts/{$layout->id}", ['font_asset_id' => $font->id])
            ->assertOk();
    }

    public function test_a_user_cannot_write_to_someone_elses_profile(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $layout = $this->layout(PageLayout::SUBJECT_USER_PROFILE, $owner->id);
        $widget = $this->widgetOn($layout);

        $this->actingAs($intruder)
            ->postJson("/api/v1/page-layouts/{$layout->id}/widgets", ['widget_type' => 'picture'])
            ->assertForbidden();
        $this->actingAs($intruder)
            ->patchJson("/api/v1/page-layout-widgets/{$widget->id}", ['width' => 3])
            ->assertForbidden();
        $this->actingAs($intruder)
            ->deleteJson("/api/v1/page-layout-widgets/{$widget->id}")
            ->assertForbidden();
    }

    public function test_an_admin_can_write_to_someone_elses_profile(): void
    {
        $owner = User::factory()->create();
        $layout = $this->layout(PageLayout::SUBJECT_USER_PROFILE, $owner->id);

        $this->actingAs($this->admin())
            ->postJson("/api/v1/page-layouts/{$layout->id}/widgets", ['widget_type' => 'picture'])
            ->assertCreated();
    }

    /**
     * The crux of the change: the two widget routes carry only a widget id,
     * so before this release the endpoint never looked at which page the
     * widget belonged to. A profile owner holding a home-page widget id has
     * to be refused on the strength of the *layout*, not the widget.
     */
    public function test_owning_a_profile_grants_nothing_on_a_widget_that_lives_on_another_page(): void
    {
        $user = User::factory()->create();
        $this->layout(PageLayout::SUBJECT_USER_PROFILE, $user->id);

        $home = $this->layout('home');
        $homeWidget = $this->widgetOn($home);

        $this->actingAs($user)
            ->patchJson("/api/v1/page-layout-widgets/{$homeWidget->id}", ['width' => 1])
            ->assertForbidden();
        $this->actingAs($user)
            ->deleteJson("/api/v1/page-layout-widgets/{$homeWidget->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('page_layout_widgets', ['id' => $homeWidget->id, 'width' => 6]);
    }

    public function test_a_profile_layout_still_requires_authentication(): void
    {
        $user = User::factory()->create();
        $layout = $this->layout(PageLayout::SUBJECT_USER_PROFILE, $user->id);

        $this->postJson("/api/v1/page-layouts/{$layout->id}/widgets", ['widget_type' => 'picture'])
            ->assertUnauthorized();
    }

    /**
     * The rule denies by default, so a subject type introduced later is
     * admin-only until somebody opens it here deliberately.
     */
    public function test_an_unknown_subject_type_is_admin_only(): void
    {
        $user = User::factory()->create();
        $layout = $this->layout('some-future-page', 42);

        $this->assertFalse($layout->canBeEditedBy($user));
        $this->assertFalse($layout->canBeEditedBy(null));
        $this->assertTrue($layout->canBeEditedBy($this->admin()));
    }

    /**
     * Group widget templates are an admin's layout-building tool over the
     * whole site, not something owning one profile hands you.
     */
    public function test_a_profile_owner_cannot_place_an_admin_group_widget_template(): void
    {
        $user = User::factory()->create();
        $layout = $this->layout(PageLayout::SUBJECT_USER_PROFILE, $user->id);
        $template = GroupWidgetTemplate::create([
            'name' => 'Hero',
            'snapshot' => ['width' => 4, 'height' => 2, 'children' => []],
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/page-layouts/{$layout->id}/group-widgets/from-template/{$template->id}")
            ->assertForbidden();
    }
}
