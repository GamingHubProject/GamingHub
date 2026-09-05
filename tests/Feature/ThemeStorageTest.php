<?php

namespace Tests\Feature;

use App\Experience\ThemeBundle;
use App\Experience\ThemeStorage;
use App\Models\Asset;
use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\InteractsWithThemes;
use Tests\TestCase;

class ThemeStorageTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithThemes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeThemeDisk();
    }

    private function storage(): ThemeStorage
    {
        return app(ThemeStorage::class);
    }

    public function test_a_theme_json_round_trips_without_losing_anything(): void
    {
        $theme = $this->makeTheme('Midnight', [
            'tokens' => ['accent' => '#4f46e5', 'surface' => '#111111'],
            'extra_tokens' => ['shadow' => '0 2px 4px #000'],
            'widget_style' => ['border_radius' => 12],
            'header_transparent' => true,
        ]);

        $bundle = $this->storage()->readBundle($theme->slug);

        $this->assertSame('#4f46e5', $bundle->tokens['accent']);
        $this->assertSame('0 2px 4px #000', $bundle->extraTokens['shadow']);
        $this->assertSame(12, $bundle->widgetStyle['border_radius']);
        $this->assertTrue($bundle->header['transparent']);
    }

    public function test_the_folder_is_the_source_of_truth_and_sync_rebuilds_the_cache_from_it(): void
    {
        $theme = $this->makeTheme('Midnight', ['tokens' => ['accent' => '#000000']]);

        // Edit the file directly, the way an import or a hand edit would.
        Storage::disk(config('assets.disk'))->put(
            $this->storage()->themePath($theme->slug, 'theme.json'),
            json_encode(['id' => $theme->slug, 'name' => 'Midnight', 'tokens' => ['accent' => '#ff0000']])
        );

        $this->assertTrue($theme->refresh()->isStale());
        $this->assertSame('#000000', $theme->payload['tokens']['accent']); // cache still behind

        $this->storage()->sync($theme);

        $this->assertSame('#ff0000', $theme->refresh()->payload['tokens']['accent']);
        $this->assertFalse($theme->isStale());
    }

    public function test_syncing_a_theme_whose_folder_vanished_keeps_the_last_known_payload(): void
    {
        // Losing a file shouldn't be more destructive than losing the
        // whole theme — the site keeps rendering with what it had.
        $theme = $this->makeTheme('Midnight', ['tokens' => ['accent' => '#4f46e5']]);
        Storage::disk(config('assets.disk'))->deleteDirectory($this->storage()->themePath($theme->slug));

        $this->storage()->sync($theme);

        $this->assertSame('#4f46e5', $theme->refresh()->payload['tokens']['accent']);
        $this->assertNull($theme->checksum);
    }

    public function test_unparseable_theme_json_does_not_throw(): void
    {
        $theme = $this->makeTheme('Midnight');
        Storage::disk(config('assets.disk'))->put($this->storage()->themePath($theme->slug, 'theme.json'), 'not json{');

        $this->assertNull($this->storage()->readBundle($theme->slug));
        $this->storage()->sync($theme); // must not throw
        $this->assertNull($theme->refresh()->checksum);
    }

    public function test_an_unknown_key_in_theme_json_is_ignored_rather_than_fatal(): void
    {
        $theme = $this->makeTheme('Midnight');
        Storage::disk(config('assets.disk'))->put(
            $this->storage()->themePath($theme->slug, 'theme.json'),
            json_encode(['id' => $theme->slug, 'name' => 'Midnight', 'somethingFromANewerBuild' => true])
        );

        $this->assertSame('Midnight', $this->storage()->readBundle($theme->slug)->name);
    }

    public function test_importing_an_asset_copies_the_file_into_the_themes_own_folder(): void
    {
        // Copied, not referenced — a theme that points outside its own
        // folder stops being self-contained the moment it's exported.
        $theme = $this->makeTheme('Midnight');
        Storage::disk(config('assets.disk'))->put('assets/shared.woff2', 'FONTBYTES');
        $source = Asset::factory()->create(['disk_path' => 'assets/shared.woff2', 'mime_type' => 'font/woff2']);

        $relative = $this->storage()->importAsset($theme, $source, 'font');

        $this->assertSame('font/shared.woff2', $relative);
        Storage::disk(config('assets.disk'))->assertExists("themes/{$theme->slug}/font/shared.woff2");
        $this->assertSame('FONTBYTES', Storage::disk(config('assets.disk'))->get("themes/{$theme->slug}/font/shared.woff2"));
        // And it shows up in the Asset Library, inside the theme's folder.
        $this->assertDatabaseHas('assets', ['disk_path' => "themes/{$theme->slug}/font/shared.woff2"]);
    }

    public function test_asset_paths_in_theme_json_stay_relative_to_the_theme_folder(): void
    {
        // The load-bearing detail for export/import: nothing in the file
        // may point outside the folder, or it won't survive the trip.
        $theme = $this->makeTheme('Midnight');
        $this->putThemeFile($theme, 'font/Inter.woff2');
        $this->storage()->writeBundle($theme, tap($theme->bundle(), function (ThemeBundle $b) {
            $b->fontFile = 'font/Inter.woff2';
        }));

        $raw = json_decode(Storage::disk(config('assets.disk'))->get($this->storage()->themePath($theme->slug, 'theme.json')), true);

        $this->assertSame('font/Inter.woff2', $raw['font']['file']);
        $this->assertStringNotContainsString('http', $raw['font']['file']);
        $this->assertStringNotContainsString($theme->slug, $raw['font']['file']);
    }

    public function test_the_cached_payload_resolves_relative_paths_to_real_urls(): void
    {
        // Resolved once at sync time so a page load never pays for it.
        $theme = $this->makeTheme('Midnight');
        $this->putThemeFile($theme, 'favicon/icon.png');
        $this->storage()->writeBundle($theme, tap($theme->bundle(), function (ThemeBundle $b) {
            $b->faviconFile = 'favicon/icon.png';
        }));

        $this->assertStringContainsString("themes/{$theme->slug}/favicon/icon.png", $theme->refresh()->payload['favicon_url']);
    }

    public function test_empty_tokens_serialize_as_an_object_not_an_array(): void
    {
        // theme.json is a published contract (an export, a registry
        // package) — a field that changes JSON type when empty is a trap
        // for whatever consumes it.
        $theme = $this->makeTheme('Midnight');

        $raw = Storage::disk(config('assets.disk'))->get($this->storage()->themePath($theme->slug, 'theme.json'));

        $this->assertStringContainsString('"tokens": {}', $raw);
    }

    public function test_creating_a_theme_builds_the_full_subfolder_set(): void
    {
        $theme = $this->storage()->createTheme('Aurora');

        foreach (['font', 'favicon', 'backgrounds'] as $sub) {
            $this->assertDatabaseHas('asset_folders', ['parent_id' => $theme->folder_id, 'slug' => $sub]);
        }
        // Admin-only for browsing; the files stay publicly fetchable,
        // which anonymous visitors depend on for the font and favicon.
        $this->assertSame('admin_only', $theme->folder->visibility);
    }

    public function test_the_migration_left_a_theme_assigned_to_the_platform(): void
    {
        // A fresh install should have something real to edit rather than
        // an empty list. Asserted through the assignment rather than a
        // literal slug: createTheme deliberately steps the slug aside if a
        // folder of that name already exists on disk.
        $assignment = \App\Models\ThemeAssignment::where('level', 'platform')->first();

        $this->assertNotNull($assignment);
        $this->assertNotNull($assignment->theme);
        $this->assertNotNull($assignment->theme->folder_id);
    }

    public function test_a_legacy_single_spacing_token_converts_into_the_scale(): void
    {
        // The pre-scale shape: one base value every consumer multiplied
        // for itself. Left alone it would linger in the admin's
        // "Additional tokens" looking like something they had added.
        $theme = $this->makeTheme('Legacy');
        Storage::disk(config('assets.disk'))->put(
            $this->storage()->themePath($theme->slug, 'theme.json'),
            json_encode(['id' => $theme->slug, 'name' => 'Legacy', 'tokens' => ['spacing' => 16, 'accent' => '#4f46e5']])
        );
        $this->storage()->sync($theme);

        $this->runSpacingMigration();

        $bundle = $theme->refresh()->bundle();
        $this->assertArrayNotHasKey('spacing', $bundle->tokens);
        $this->assertArrayNotHasKey('spacing', $bundle->extraTokens);
        $this->assertSame(16, $bundle->tokens['space-normal']);
        $this->assertSame(8, $bundle->tokens['space-tight']);
        $this->assertSame(24, $bundle->tokens['space-loose']);
        $this->assertSame(32, $bundle->tokens['space-section']);
        // Everything else is untouched.
        $this->assertSame('#4f46e5', $bundle->tokens['accent']);
    }

    public function test_the_spacing_conversion_leaves_a_theme_that_never_had_one_alone(): void
    {
        $theme = $this->makeTheme('Modern', ['tokens' => ['space-normal' => 14]]);

        $this->runSpacingMigration();

        $bundle = $theme->refresh()->bundle();
        $this->assertSame(14, $bundle->tokens['space-normal']);
        $this->assertArrayNotHasKey('space-tight', $bundle->tokens);
    }

    private function runSpacingMigration(): void
    {
        (require database_path('migrations/2026_09_05_100000_replace_single_spacing_token_with_a_scale.php'))->up();
    }
}
