<?php

use App\Experience\ThemeBundle;
use App\Experience\ThemeStorage;
use App\Models\Theme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two changes that belong together, because the sidebar can't be a real
 * region without both.
 *
 * 1. The theme's `site` block gets header and sidebar as symmetrical,
 *    independently styled regions. `header_transparent` and
 *    `sidebar_behavior` used to sit loose at its top level; adding six
 *    more settings each in that shape would have been unreadable, and
 *    nothing in it could express "sidebar solid, header transparent".
 *
 * 2. `navigation_links` gains a `surface`, so the header and sidebar can
 *    show genuinely different things — a top bar of sections, a sidebar
 *    browsing every game.
 *
 * Neither changes how an existing site looks. Old flat settings are folded
 * into the new regions, every existing link becomes a header link, and
 * nav_mirror defaults to sidebar_follows_header — which is precisely
 * today's behaviour (both surfaces, same tree) expressed in the new model
 * without duplicating a single row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_links', function (Blueprint $table) {
            $table->string('surface')->default('header')->after('parent_id');
            $table->index(['surface', 'parent_id', 'position']);
        });

        $storage = app(ThemeStorage::class);

        foreach (Theme::all() as $theme) {
            $raw = $this->rawSite($storage, $theme);
            if ($raw === null) {
                continue;
            }

            $bundle = $theme->bundle();

            // Carry the old flat values into the region that owned them,
            // so a theme that had a transparent header keeps one.
            $bundle->header = ThemeBundle::HEADER_DEFAULTS;
            $bundle->header['transparent'] = (bool) ($raw['header_transparent'] ?? false);

            $bundle->sidebar = ThemeBundle::SIDEBAR_DEFAULTS;
            $bundle->sidebar['behavior'] = in_array($raw['sidebar_behavior'] ?? null, ['always', 'auto-hide', 'toggle'], true)
                ? $raw['sidebar_behavior']
                : 'always';

            $storage->writeBundle($theme, $bundle);
        }
    }

    /**
     * Read the file directly rather than through ThemeBundle: by the time
     * this runs the model already describes the new shape and would have
     * dropped the flat keys we're here to migrate.
     */
    private function rawSite(ThemeStorage $storage, Theme $theme): ?array
    {
        $disk = \Illuminate\Support\Facades\Storage::disk(config('assets.disk'));
        $path = $storage->themePath($theme->slug, 'theme.json');

        if (! $disk->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) $disk->get($path), true);

        return is_array($decoded['site'] ?? null) ? $decoded['site'] : [];
    }

    public function down(): void
    {
        Schema::table('navigation_links', function (Blueprint $table) {
            $table->dropIndex(['surface', 'parent_id', 'position']);
            $table->dropColumn('surface');
        });
    }
};
