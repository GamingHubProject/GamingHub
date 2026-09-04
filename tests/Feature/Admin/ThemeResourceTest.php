<?php

namespace Tests\Feature\Admin;

use App\Experience\ThemeStorage;
use App\Filament\Resources\ThemeResource\Pages\CreateTheme;
use App\Filament\Resources\ThemeResource\Pages\EditTheme;
use App\Filament\Resources\ThemeResource\Pages\ListThemes;
use App\Models\AssetFolder;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\InteractsWithThemes;
use Tests\TestCase;

class ThemeResourceTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithThemes;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
        $this->fakeThemeDisk();
    }

    public function test_can_list_themes(): void
    {
        $this->makeTheme('Midnight');

        Livewire::test(ListThemes::class)->assertSuccessful();
    }

    public function test_creating_a_theme_creates_its_folder_and_theme_json(): void
    {
        Livewire::test(CreateTheme::class)
            ->fillForm(['name' => 'Midnight', 'tokens' => ['accent' => '#4f46e5']])
            ->call('create')
            ->assertHasNoFormErrors();

        $theme = Theme::where('name', 'Midnight')->firstOrFail();
        $this->assertSame('midnight', $theme->slug);

        // The folder is the theme — a row without one would be a theme
        // with nowhere to put its font, favicon or backgrounds.
        Storage::disk(config('assets.disk'))->assertExists('themes/midnight/theme.json');
        foreach (['font', 'favicon', 'backgrounds'] as $sub) {
            $this->assertDatabaseHas('asset_folders', ['slug' => $sub, 'parent_id' => $theme->folder_id]);
        }
    }

    public function test_a_created_theme_writes_its_tokens_into_theme_json(): void
    {
        Livewire::test(CreateTheme::class)
            ->fillForm(['name' => 'Midnight', 'tokens' => ['accent' => '#4f46e5', 'surface' => '#111111']])
            ->call('create');

        $bundle = Theme::where('slug', 'midnight')->firstOrFail()->bundle();

        $this->assertSame('#4f46e5', $bundle->tokens['accent']);
        $this->assertSame('#111111', $bundle->tokens['surface']);
    }

    public function test_editing_fills_from_the_folder_not_from_the_database_row(): void
    {
        $theme = $this->makeTheme('Midnight', ['tokens' => ['accent' => '#4f46e5'], 'header_transparent' => true]);

        Livewire::test(EditTheme::class, ['record' => $theme->getRouteKey()])
            ->assertFormSet(fn (array $state) => $state['tokens']['accent'] === '#4f46e5' && $state['header_transparent'] === true);
    }

    public function test_saving_writes_back_to_theme_json_and_refreshes_the_cached_row(): void
    {
        $theme = $this->makeTheme('Midnight', ['tokens' => ['accent' => '#4f46e5']]);
        $before = $theme->checksum;

        Livewire::test(EditTheme::class, ['record' => $theme->getRouteKey()])
            ->fillForm(['name' => 'Midnight', 'tokens' => ['accent' => '#dc2626']])
            ->call('save')
            ->assertHasNoFormErrors();

        $theme->refresh();
        $this->assertSame('#dc2626', $theme->bundle()->tokens['accent']);
        // The cached payload the API serves must move with the file.
        $this->assertSame('#dc2626', $theme->payload['tokens']['accent']);
        $this->assertNotSame($before, $theme->checksum);
    }

    public function test_a_blank_colour_is_stored_as_absent_rather_than_an_empty_token(): void
    {
        // An empty string would override the theme beneath with nothing,
        // which is not what clearing a colour field means.
        $theme = $this->makeTheme('Midnight', ['tokens' => ['accent' => '#4f46e5']]);

        Livewire::test(EditTheme::class, ['record' => $theme->getRouteKey()])
            ->fillForm(['name' => 'Midnight', 'tokens' => ['accent' => null]])
            ->call('save');

        $this->assertArrayNotHasKey('accent', $theme->refresh()->bundle()->tokens);
    }

    public function test_widget_style_defaults_round_trip_through_the_theme(): void
    {
        $theme = $this->makeTheme('Midnight');

        Livewire::test(EditTheme::class, ['record' => $theme->getRouteKey()])
            ->fillForm([
                'name' => 'Midnight',
                'widgetStyle' => ['border_radius' => 12, 'background_type' => 'pattern', 'background_pattern' => 'dots'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $style = $theme->refresh()->bundle()->widgetStyle;
        $this->assertSame(12, (int) $style['border_radius']);
        $this->assertSame('pattern', $style['background_type']);
    }

    public function test_a_token_outside_the_contract_survives_via_extra_tokens(): void
    {
        $theme = $this->makeTheme('Midnight');

        Livewire::test(EditTheme::class, ['record' => $theme->getRouteKey()])
            ->fillForm(['name' => 'Midnight', 'extra_tokens' => ['shadow-strong' => '0 4px 8px #000']])
            ->call('save');

        $bundle = $theme->refresh()->bundle();
        $this->assertSame('0 4px 8px #000', $bundle->extraTokens['shadow-strong']);
        // Extra tokens still reach the SPA — they're real CSS variables.
        $this->assertArrayHasKey('shadow-strong', $bundle->allTokens());
    }

    public function test_two_themes_with_the_same_name_get_distinct_folders(): void
    {
        $first = $this->makeTheme('Midnight');
        $second = $this->makeTheme('Midnight');

        $this->assertNotSame($first->slug, $second->slug);
        Storage::disk(config('assets.disk'))->assertExists("themes/{$second->slug}/theme.json");
    }

    public function test_deleting_a_theme_removes_its_folder_too(): void
    {
        $theme = $this->makeTheme('Midnight');
        $folderId = $theme->folder_id;

        app(ThemeStorage::class)->deleteTheme($theme);

        Storage::disk(config('assets.disk'))->assertMissing("themes/{$theme->slug}/theme.json");
        $this->assertDatabaseMissing('themes', ['id' => $theme->id]);
        $this->assertNull(AssetFolder::find($folderId));
    }
}
