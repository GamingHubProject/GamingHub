<?php

namespace App\Experience;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\SiteOption;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The folder is the theme; this class is the only thing that knows it.
 *
 * A theme lives at /themes/{slug}/ on the assets disk, holding a
 * theme.json plus the font, favicon and background files it references by
 * relative path. That makes a theme self-contained — export is "zip the
 * folder", import is "unzip and sync", and a package for the registry is
 * the same zip with theme.json doubling as its manifest.
 *
 * The `themes` DB row is an index, not the truth: it caches the parsed
 * bundle in `payload` (with asset paths already resolved to URLs) so that
 * /api/v1/theme — hit on every page load and every scope change — stays a
 * single cheap query instead of a disk read and a JSON parse. `checksum`
 * is how a folder edited behind the app's back gets noticed; sync() is how
 * it gets repaired.
 */
class ThemeStorage
{
    public const ROOT = 'themes';

    private const SUBFOLDERS = ['font', 'favicon', 'backgrounds'];

    private function disk()
    {
        return Storage::disk(config('assets.disk'));
    }

    public function themePath(string $slug, string $relative = ''): string
    {
        return trim(self::ROOT.'/'.$slug.'/'.ltrim($relative, '/'), '/');
    }

    // --- Reading -----------------------------------------------------

    public function readBundle(string $slug): ?ThemeBundle
    {
        $path = $this->themePath($slug, 'theme.json');
        if (! $this->disk()->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) $this->disk()->get($path), true);

        return is_array($decoded) ? ThemeBundle::fromArray($decoded) : null;
    }

    // --- Writing -----------------------------------------------------

    /**
     * Write theme.json and re-sync the index row. The only supported way
     * to change a theme's configuration — going straight to the disk
     * leaves `checksum` stale until the next sync, and going straight to
     * the DB row gets overwritten by it.
     */
    public function writeBundle(Theme $theme, ThemeBundle $bundle): Theme
    {
        $json = json_encode($bundle->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->disk()->put($this->themePath($theme->slug, 'theme.json'), $json);

        return $this->sync($theme);
    }

    /**
     * Refresh the index row from what's actually on disk. Safe to call
     * repeatedly; that's the point — it's also how `themes:sync` repairs a
     * folder someone edited directly.
     */
    public function sync(Theme $theme): Theme
    {
        $bundle = $this->readBundle($theme->slug);

        if (! $bundle) {
            // The folder is gone or unreadable. Keep the last known good
            // payload rather than blanking the site's styling — a missing
            // file shouldn't be more destructive than a missing theme.
            $theme->forceFill(['checksum' => null, 'synced_at' => now()])->save();

            return $theme;
        }

        $theme->forceFill([
            'name' => $bundle->name,
            'payload' => $this->resolvePayload($theme, $bundle),
            'checksum' => md5((string) $this->disk()->get($this->themePath($theme->slug, 'theme.json'))),
            'synced_at' => now(),
        ])->save();

        return $theme;
    }

    /**
     * The cached payload the API serves: the bundle plus the two things a
     * client can't work out for itself — real URLs for the font and
     * favicon, resolved from their folder-relative paths. Resolved here,
     * once per save, rather than on every request.
     */
    private function resolvePayload(Theme $theme, ThemeBundle $bundle): array
    {
        return [
            'tokens' => $bundle->allTokens(),
            'font' => $bundle->fontFile ? [
                // Synthetic and stable, same convention the font system
                // already used — never shown to an admin, just needs to be
                // a collision-free CSS identifier.
                'family' => $bundle->fontFamily ?: "gh-theme-{$theme->id}",
                'url' => $this->fileUrl($theme->slug, $bundle->fontFile),
            ] : null,
            'favicon_url' => $bundle->faviconFile ? $this->fileUrl($theme->slug, $bundle->faviconFile) : null,
            'widgetStyle' => $bundle->widgetStyle,
            'site' => ['header_transparent' => $bundle->headerTransparent],
        ];
    }

    private function fileUrl(string $slug, string $relative): ?string
    {
        $path = $this->themePath($slug, $relative);

        return $this->disk()->exists($path) ? $this->disk()->url($path) : null;
    }

    // --- Creating ----------------------------------------------------

    /**
     * A theme's folder, its subfolders and its AssetFolder rows. The
     * AssetFolder rows are what make the theme's files visible and
     * manageable in the Asset Library UI — the files themselves are just
     * the disk paths above.
     *
     * Folders are admin_only, which governs who can BROWSE them. The files
     * stay publicly fetchable off the public disk, which they must be:
     * anonymous visitors load the font and favicon on every page. Same
     * arrangement the Fonts folder has always had.
     */
    public function createTheme(string $name, ?int $createdBy = null): Theme
    {
        $slug = $this->uniqueSlug($name);
        $root = $this->ensureFolder(null, self::ROOT, 'Themes', $createdBy);
        $themeFolder = $this->ensureFolder($root, $slug, $name, $createdBy);

        foreach (self::SUBFOLDERS as $sub) {
            $this->ensureFolder($themeFolder, $sub, Str::headline($sub), $createdBy);
        }

        $theme = Theme::create(['name' => $name, 'slug' => $slug, 'folder_id' => $themeFolder->id]);

        return $this->writeBundle($theme, new ThemeBundle(id: $slug, name: $name));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'theme';
        $slug = $base;
        $n = 2;
        while (Theme::where('slug', $slug)->exists() || $this->disk()->exists($this->themePath($slug))) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    private function ensureFolder(?AssetFolder $parent, string $slug, string $name, ?int $createdBy): AssetFolder
    {
        return AssetFolder::firstOrCreate(
            ['parent_id' => $parent?->id, 'slug' => $slug],
            [
                'name' => $name,
                'visibility' => 'admin_only',
                'path' => AssetFolder::buildPath($parent, $slug),
                'created_by' => $createdBy ?? User::query()->value('id'),
            ]
        );
    }

    /**
     * Copy an existing library asset into one of this theme's subfolders,
     * returning the folder-relative path to store in theme.json. Copied,
     * not referenced: a theme that points at a file outside its own folder
     * stops being self-contained the moment it's exported.
     */
    public function importAsset(Theme $theme, Asset $source, string $subfolder): string
    {
        $filename = basename($source->disk_path);
        $relative = $subfolder.'/'.$filename;
        $target = $this->themePath($theme->slug, $relative);

        if ($this->disk()->exists($source->disk_path) && ! $this->disk()->exists($target)) {
            $this->disk()->put($target, (string) $this->disk()->get($source->disk_path));
        }

        $folder = AssetFolder::where('parent_id', $theme->folder_id)->where('slug', $subfolder)->first();

        Asset::firstOrCreate(
            ['disk_path' => $target],
            [
                'url' => $this->disk()->url($target),
                'mime_type' => $source->mime_type,
                'size' => $source->size,
                'width' => $source->width,
                'height' => $source->height,
                'alt_text' => $source->alt_text,
                'folder_id' => $folder?->id,
            ]
        );

        return $relative;
    }

    /**
     * A full, independent copy: new folder, every file copied across, and
     * theme.json rewritten under the new identity.
     *
     * The files have to be copied rather than shared. Two themes pointing
     * at one set of files would mean editing either one silently changed
     * the other, and neither could be exported on its own — which is the
     * property the whole folder model exists to guarantee.
     */
    public function duplicateTheme(Theme $source, string $name): Theme
    {
        $copy = $this->createTheme($name);

        foreach ($this->disk()->allFiles($this->themePath($source->slug)) as $path) {
            $relative = ltrim(substr($path, strlen($this->themePath($source->slug))), '/');
            if ($relative === 'theme.json') {
                continue; // rewritten below, under the copy's own id
            }
            $this->disk()->put($this->themePath($copy->slug, $relative), (string) $this->disk()->get($path));
        }

        $bundle = $source->bundle();
        $bundle->id = $copy->slug;
        $bundle->name = $name;

        return $this->writeBundle($copy, $bundle);
    }

    /** Files already sitting in one of a theme's subfolders, for the admin picker. */
    public function filesIn(Theme $theme, string $subfolder): array
    {
        $paths = $this->disk()->files($this->themePath($theme->slug, $subfolder));

        return collect($paths)
            ->mapWithKeys(fn (string $p) => [$subfolder.'/'.basename($p) => basename($p)])
            ->all();
    }

    public function deleteTheme(Theme $theme): void
    {
        $this->disk()->deleteDirectory($this->themePath($theme->slug));
        Asset::where('disk_path', 'like', $this->themePath($theme->slug).'/%')->delete();
        AssetFolder::where('parent_id', $theme->folder_id)->delete();
        AssetFolder::where('id', $theme->folder_id)->delete();
        $theme->delete();
    }

    // --- Migration ---------------------------------------------------

    /**
     * Called once, from the restructure migration. Turns whatever the
     * install currently has — a platform Theme row's tokens plus the font,
     * favicon, header and widget-style settings Phase A put in SiteOption
     * — into a real folder-backed theme, then splits every legacy row's
     * scope into a theme_assignments record.
     *
     * Written against the query builder rather than the Eloquent models on
     * purpose: it runs mid-migration, when `themes` still has its old
     * columns and the models already describe the new shape.
     */
    public function migrateLegacyThemes(): void
    {
        $values = SiteOption::current()->values ?? [];
        $legacy = DB::table('themes')->get();

        $platform = $legacy->firstWhere('level', 'platform');
        $slug = $this->uniqueSlug($platform->name ?? 'Default');
        $rootFolder = $this->ensureFolder(null, self::ROOT, 'Themes', null);
        $themeFolder = $this->ensureFolder($rootFolder, $slug, $platform->name ?? 'Default', null);
        foreach (self::SUBFOLDERS as $sub) {
            $this->ensureFolder($themeFolder, $sub, Str::headline($sub), null);
        }

        if ($platform) {
            DB::table('themes')->where('id', $platform->id)
                ->update(['slug' => $slug, 'folder_id' => $themeFolder->id, 'name' => $platform->name]);
            $themeId = $platform->id;
        } else {
            $themeId = DB::table('themes')->insertGetId([
                'name' => 'Default', 'slug' => $slug, 'folder_id' => $themeFolder->id,
                // `level` is still a NOT NULL column at this point in the
                // migration — it's dropped immediately after this runs.
                'level' => 'platform',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $theme = Theme::find($themeId);
        $tokens = json_decode($platform->tokens ?? '[]', true) ?: [];

        $bundle = new ThemeBundle(
            id: $slug,
            name: $theme->name,
            tokens: array_intersect_key($tokens, ThemeBundle::contractTokens()),
            extraTokens: array_diff_key($tokens, ThemeBundle::contractTokens()),
            widgetStyle: $values['widget_style_defaults'] ?? [],
            headerTransparent: (bool) ($values['header_transparent'] ?? false),
        );

        // Copy the referenced font/favicon in, so the theme owns its
        // assets rather than pointing at the shared Fonts folder.
        if ($fontAsset = Asset::find($values['font_asset_id'] ?? null)) {
            $bundle->fontFile = $this->importAsset($theme, $fontAsset, 'font');
            $bundle->fontFamily = "gh-font-{$fontAsset->id}";
        }
        if ($faviconAsset = Asset::find($values['favicon_asset_id'] ?? null)) {
            $bundle->faviconFile = $this->importAsset($theme, $faviconAsset, 'favicon');
        }

        $this->writeBundle($theme, $bundle);
        ThemeAssignment::updateOrCreate(['level' => 'platform', 'game_id' => null, 'server_id' => null], ['theme_id' => $theme->id]);

        // Game/server themes keep their tokens but become their own
        // bundles, each with an assignment pointing at the scope the row
        // used to carry itself.
        foreach ($legacy->where('level', '!=', 'platform') as $row) {
            $scopedSlug = $this->uniqueSlug($row->name);
            $folder = $this->ensureFolder($rootFolder, $scopedSlug, $row->name, null);
            foreach (self::SUBFOLDERS as $sub) {
                $this->ensureFolder($folder, $sub, Str::headline($sub), null);
            }
            DB::table('themes')->where('id', $row->id)->update(['slug' => $scopedSlug, 'folder_id' => $folder->id]);

            $scopedTokens = json_decode($row->tokens ?? '[]', true) ?: [];
            $scoped = Theme::find($row->id);
            $this->writeBundle($scoped, new ThemeBundle(
                id: $scopedSlug,
                name: $row->name,
                tokens: array_intersect_key($scopedTokens, ThemeBundle::contractTokens()),
                extraTokens: array_diff_key($scopedTokens, ThemeBundle::contractTokens()),
            ));

            ThemeAssignment::updateOrCreate(
                ['level' => $row->level, 'game_id' => $row->game_id, 'server_id' => $row->server_id],
                ['theme_id' => $row->id]
            );
        }

        // The moved keys are now the theme's, not the site's.
        SiteOption::current()->update([
            'values' => collect($values)
                ->except(['font_asset_id', 'favicon_asset_id', 'header_transparent', 'widget_style_defaults'])
                ->all(),
        ]);
    }
}
