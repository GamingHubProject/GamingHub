<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SiteOptions;
use App\Models\SiteOption;
use App\Models\User;
use App\Profiles\ProfileWidgets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The policy half of profile widget curation. The capability half lives in
 * the SPA's registry and is not an admin's to change — see
 * App\Profiles\ProfileWidgets for why the two are kept apart.
 */
class ProfileWidgetOptionTest extends TestCase
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

    public function test_a_site_that_has_never_touched_the_setting_allows_the_starter_set(): void
    {
        $this->assertSame(ProfileWidgets::DEFAULT_ENABLED, ProfileWidgets::enabled());
    }

    public function test_an_admin_can_narrow_the_list(): void
    {
        Livewire::test(SiteOptions::class)
            ->fillForm([ProfileWidgets::OPTION_KEY => ['profile-avatar', 'profile-bio']])
            ->call('save');

        $this->assertSame(['profile-avatar', 'profile-bio'], ProfileWidgets::enabled());
    }

    /**
     * The form is pre-filled with the starter set for a site that has
     * never opened this page — otherwise saving an unrelated setting would
     * silently store "no widgets allowed".
     */
    public function test_saving_an_unrelated_setting_does_not_empty_the_list(): void
    {
        Livewire::test(SiteOptions::class)
            ->fillForm(['site_name' => 'Gaming Hub'])
            ->call('save');

        $this->assertSame(ProfileWidgets::DEFAULT_ENABLED, ProfileWidgets::enabled());
    }

    /**
     * A stored list can outlive the widget it names. Serving that type
     * would put an entry in somebody's picker that nothing can render.
     */
    public function test_a_type_that_no_longer_exists_is_dropped_on_read(): void
    {
        SiteOption::current()->update(['values' => [ProfileWidgets::OPTION_KEY => ['profile-bio', 'shop-basket']]]);

        $this->assertSame(['profile-bio'], ProfileWidgets::enabled());
    }

    public function test_every_option_offered_is_a_widget_the_frontend_can_actually_render(): void
    {
        $registry = file_get_contents(base_path('spa/src/widgets/pageLayout/index.ts'));

        foreach (array_keys(ProfileWidgets::CAPABLE) as $type) {
            $this->assertStringContainsString("type: '{$type}'", $registry, "The admin offers '{$type}' but no widget registers it.");
        }
    }
}
