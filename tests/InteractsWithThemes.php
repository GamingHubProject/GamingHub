<?php

namespace Tests;

use App\Experience\ThemeBundle;
use App\Experience\ThemeStorage;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use Illuminate\Support\Facades\Storage;

/**
 * Themes are folders on the assets disk (see App\Experience\ThemeStorage),
 * so any test that touches one has to fake that disk or it writes into the
 * developer's real storage directory.
 *
 * Note the ordering trap this exists to hide: the restructure migration
 * creates a `default` theme folder, and it runs *before* a test body can
 * call Storage::fake — which wipes it. So faking the disk always means
 * re-writing the bundles of any theme rows the migration left behind,
 * which is what fakeThemeDisk() does.
 */
trait InteractsWithThemes
{
    protected function fakeThemeDisk(): void
    {
        Storage::fake(config('assets.disk'));

        $storage = app(ThemeStorage::class);
        foreach (Theme::all() as $theme) {
            $storage->writeBundle($theme, new ThemeBundle(id: $theme->slug, name: $theme->name));
        }
    }

    /** A theme with a real folder behind it, assigned to a scope if asked. */
    protected function makeTheme(string $name, array $bundle = [], ?string $level = null, ?int $gameId = null, ?int $serverId = null): Theme
    {
        $storage = app(ThemeStorage::class);
        $theme = $storage->createTheme($name);

        $storage->writeBundle($theme, new ThemeBundle(
            id: $theme->slug,
            name: $name,
            tokens: $bundle['tokens'] ?? [],
            extraTokens: $bundle['extra_tokens'] ?? [],
            fontFile: $bundle['font_file'] ?? null,
            fontFamily: $bundle['font_family'] ?? null,
            faviconFile: $bundle['favicon_file'] ?? null,
            widgetStyle: $bundle['widget_style'] ?? [],
            header: array_merge(\App\Experience\ThemeBundle::HEADER_DEFAULTS, ['transparent' => $bundle['header_transparent'] ?? false]),
            sidebar: \App\Experience\ThemeBundle::SIDEBAR_DEFAULTS,
            navMirror: $bundle['nav_mirror'] ?? 'none',
        ));

        if ($level) {
            ThemeAssignment::assign($level, $theme->id, $gameId, $serverId);
        }

        return $theme->refresh();
    }

    /** Put a real file inside one of a theme's subfolders. */
    protected function putThemeFile(Theme $theme, string $relative, string $contents = 'x'): string
    {
        Storage::disk(config('assets.disk'))->put(
            app(ThemeStorage::class)->themePath($theme->slug, $relative),
            $contents
        );

        return $relative;
    }
}
