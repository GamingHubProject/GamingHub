<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\InstalledPackageResource\Pages\ListInstalledPackages;
use App\Models\InstalledPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\InteractsWithThemes;
use Tests\TestCase;

/**
 * A theme package shows up in the installed list, but the list has to stop
 * treating it like code — a theme has no enable/disable state.
 */
class InstalledThemePackageTest extends TestCase
{
    use InteractsWithThemes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
        $this->fakeThemeDisk();
    }

    private function themeRecord(?string $themeSlug = null): InstalledPackage
    {
        return InstalledPackage::create([
            'slug' => 'aurora-theme',
            'kind' => InstalledPackage::KIND_THEME,
            'name' => 'Aurora',
            'version' => '1.0.0',
            'status' => InstalledPackage::STATUS_INSTALLED,
            'manifest' => $themeSlug ? ['theme_slug' => $themeSlug] : [],
            'installed_at' => now(),
        ]);
    }

    private function extensionRecord(): InstalledPackage
    {
        return InstalledPackage::create([
            'slug' => 'basic-connectors',
            'kind' => InstalledPackage::KIND_EXTENSION,
            'name' => 'Basic Connectors',
            'version' => '0.1.000',
            'status' => 'disabled',
            'installed_at' => now(),
        ]);
    }

    public function test_a_theme_package_is_listed(): void
    {
        $record = $this->themeRecord();

        Livewire::test(ListInstalledPackages::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$record]);
    }

    public function test_a_theme_is_not_offered_an_enable_toggle(): void
    {
        // It would change a string nothing reads. A theme goes live through
        // a ThemeAssignment, not through this list.
        $theme = $this->themeRecord();

        Livewire::test(ListInstalledPackages::class)
            ->assertTableActionHidden('toggle', $theme);
    }

    public function test_an_extension_still_gets_its_enable_toggle(): void
    {
        $extension = $this->extensionRecord();

        Livewire::test(ListInstalledPackages::class)
            ->assertTableActionVisible('toggle', $extension);
    }

    public function test_a_theme_row_links_to_the_theme_it_installed(): void
    {
        $theme = $this->makeTheme('Aurora');
        $record = $this->themeRecord($theme->slug);

        Livewire::test(ListInstalledPackages::class)
            ->assertTableActionVisible('manageTheme', $record);
    }

    public function test_the_link_disappears_when_the_theme_has_been_deleted(): void
    {
        // An admin can delete a theme without touching this row, and a link
        // to a theme that isn't there is worse than no link.
        $record = $this->themeRecord('a-theme-that-was-deleted');

        Livewire::test(ListInstalledPackages::class)
            ->assertTableActionHidden('manageTheme', $record);
    }

    public function test_an_extension_gets_no_manage_theme_link(): void
    {
        $extension = $this->extensionRecord();

        Livewire::test(ListInstalledPackages::class)
            ->assertTableActionHidden('manageTheme', $extension);
    }
}
