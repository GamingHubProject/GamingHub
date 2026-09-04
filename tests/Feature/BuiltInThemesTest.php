<?php

namespace Tests\Feature;

use App\Experience\BuiltInThemes;
use App\Experience\ThemeBundle;
use App\Experience\ThemeStorage;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\InteractsWithThemes;
use Tests\TestCase;

class BuiltInThemesTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithThemes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeThemeDisk();
        // Rows *and* folders: the migration seeds built-ins, and a
        // leftover /themes/nebula/ on disk would make createTheme step the
        // slug aside to nebula-2 even with the row gone.
        \Illuminate\Support\Facades\Storage::disk(config('assets.disk'))->deleteDirectory(ThemeStorage::ROOT);
        Theme::query()->delete();
        ThemeAssignment::query()->delete();
    }

    private function seedBuiltIns(): void
    {
        BuiltInThemes::seed(app(ThemeStorage::class));
    }

    public function test_seeding_creates_every_built_in_theme_with_a_folder(): void
    {
        $this->seedBuiltIns();

        foreach (array_keys(BuiltInThemes::all()) as $slug) {
            $this->assertDatabaseHas('themes', ['slug' => $slug, 'is_builtin' => true]);
            $this->assertNotNull(app(ThemeStorage::class)->readBundle($slug), "no theme.json for {$slug}");
        }
    }

    public function test_seeding_is_idempotent_and_does_not_overwrite_an_edited_built_in(): void
    {
        // Safe to run on every deploy: an admin who has recoloured Nebula
        // keeps their work.
        $this->seedBuiltIns();
        $nebula = Theme::where('slug', 'nebula')->firstOrFail();
        $bundle = $nebula->bundle();
        $bundle->tokens['accent'] = '#ff0000';
        app(ThemeStorage::class)->writeBundle($nebula, $bundle);

        $this->seedBuiltIns();

        $this->assertSame('#ff0000', $nebula->refresh()->bundle()->tokens['accent']);
        $this->assertSame(1, Theme::where('slug', 'nebula')->count());
    }

    public function test_seeding_assigns_a_theme_to_the_platform_only_when_nothing_is_assigned(): void
    {
        $this->seedBuiltIns();
        $this->assertSame('nebula', ThemeAssignment::where('level', 'platform')->first()?->theme?->slug);
    }

    public function test_seeding_never_changes_a_site_that_already_has_a_theme_in_use(): void
    {
        // The upgrade path: an existing install gains options, it does not
        // get its look replaced out from under it.
        $existing = $this->makeTheme('Mine', ['tokens' => ['accent' => '#123456']], ThemeAssignment::LEVEL_PLATFORM);

        $this->seedBuiltIns();

        $this->assertSame($existing->id, ThemeAssignment::where('level', 'platform')->first()?->theme_id);
    }

    public function test_every_built_in_defines_the_whole_colour_contract(): void
    {
        // A half-filled palette falls through to the hardcoded look for
        // whatever it misses, which is exactly the generic default these
        // exist to avoid.
        $this->seedBuiltIns();

        foreach (array_keys(BuiltInThemes::all()) as $slug) {
            $tokens = Theme::where('slug', $slug)->firstOrFail()->bundle()->tokens;
            foreach (array_keys(ThemeBundle::COLOR_TOKENS) as $token) {
                $this->assertArrayHasKey($token, $tokens, "{$slug} is missing {$token}");
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $tokens[$token], "{$slug}.{$token}");
            }
        }
    }

    public function test_every_built_in_sets_shape_and_the_whole_spacing_scale(): void
    {
        $this->seedBuiltIns();

        foreach (array_keys(BuiltInThemes::all()) as $slug) {
            $tokens = Theme::where('slug', $slug)->firstOrFail()->bundle()->tokens;
            $this->assertArrayHasKey('radius', $tokens, "{$slug} has no radius");
            // The direction calls for generous rounding, not the 8px the
            // components fall back to.
            $this->assertGreaterThanOrEqual(12, $tokens['radius'], "{$slug} rounding is too tight for the design direction");

            foreach (ThemeBundle::SPACING_STEPS as $step) {
                $this->assertArrayHasKey($step, $tokens, "{$slug} is missing {$step}");
            }
        }
    }

    public function test_every_built_ins_spacing_steps_increase(): void
    {
        // A scale whose steps aren't ordered isn't a scale — a widget
        // reaching for "loose" would sometimes get less room than one
        // reaching for "normal".
        $this->seedBuiltIns();

        foreach (array_keys(BuiltInThemes::all()) as $slug) {
            $tokens = Theme::where('slug', $slug)->firstOrFail()->bundle()->tokens;
            $values = array_map(fn ($step) => $tokens[$step], ThemeBundle::SPACING_STEPS);
            $sorted = $values;
            sort($sorted);

            $this->assertSame($sorted, $values, "{$slug}'s spacing steps are not in ascending order");
            $this->assertSame(count($values), count(array_unique($values)), "{$slug} has duplicate spacing steps");
        }
    }

    public function test_the_spacing_steps_are_named_by_job_not_by_size(): void
    {
        // These labels are what an admin reads; "how much room between
        // sections" is answerable, "space-lg" is not.
        foreach (ThemeBundle::SPACING_STEPS as $step) {
            $this->assertArrayHasKey($step, ThemeBundle::SCALE_TOKENS);
            $this->assertNotEmpty(ThemeBundle::SCALE_TOKENS[$step]['label']);
        }
    }

    public function test_scale_tokens_reach_css_with_a_unit_but_stay_numbers_on_disk(): void
    {
        $this->seedBuiltIns();
        $nebula = Theme::where('slug', 'nebula')->firstOrFail();

        // theme.json keeps the honest value, for an importing site to reason about...
        $this->assertSame(14, $nebula->bundle()->tokens['radius']);
        $this->assertSame(20, $nebula->bundle()->tokens['space-loose']);
        // ...and the payload the browser consumes is CSS-ready.
        $this->assertSame('14px', $nebula->payload['tokens']['radius']);
        $this->assertSame('20px', $nebula->payload['tokens']['space-loose']);
    }

    public function test_a_value_that_already_carries_a_unit_is_passed_through(): void
    {
        $theme = $this->makeTheme('Custom', ['tokens' => ['radius' => '0.5rem']]);

        $this->assertSame('0.5rem', $theme->payload['tokens']['radius']);
    }

    public function test_each_built_in_ships_widget_defaults_so_chrome_matches_the_palette(): void
    {
        // Borderless cards on a lifted surface read very differently from
        // bordered ones; that belongs to the theme, not to each widget.
        $this->seedBuiltIns();

        $nebula = Theme::where('slug', 'nebula')->firstOrFail()->bundle();
        $daybreak = Theme::where('slug', 'daybreak')->firstOrFail()->bundle();

        $this->assertFalse($nebula->widgetStyle['border_enabled']);
        $this->assertTrue($daybreak->widgetStyle['border_enabled']);
    }

    public function test_the_light_built_in_keeps_its_accent_readable_on_its_own_surface(): void
    {
        // Nebula's violet at the same lightness would be illegible on a
        // near-white ground, which is why Daybreak darkens it.
        $this->seedBuiltIns();
        $tokens = Theme::where('slug', 'daybreak')->firstOrFail()->bundle()->tokens;

        $this->assertLessThan(
            $this->luminance($tokens['surface']),
            $this->luminance($tokens['accent']),
            'the light theme\'s accent must be darker than its surface'
        );
    }

    public function test_every_built_in_has_readable_body_text_against_its_own_background(): void
    {
        $this->seedBuiltIns();

        foreach (array_keys(BuiltInThemes::all()) as $slug) {
            $tokens = Theme::where('slug', $slug)->firstOrFail()->bundle()->tokens;
            $ratio = $this->contrast($tokens['text'], $tokens['background']);
            // WCAG AA for normal text — a shipped default has no excuse
            // for failing it.
            $this->assertGreaterThanOrEqual(4.5, $ratio, "{$slug}: text on background is only {$ratio}:1");
        }
    }

    private function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(
            fn ($c) => ($v = hexdec($c) / 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4,
            str_split(ltrim($hex, '#'), 2)
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private function contrast(string $a, string $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return round((max($la, $lb) + 0.05) / (min($la, $lb) + 0.05), 2);
    }
}
