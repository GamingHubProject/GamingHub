<?php

namespace App\Experience;

use App\Models\Asset;
use App\Models\PageLayout;
use App\Models\SiteOption;
use App\Models\Theme;
use App\Models\ThemeAssignment;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;

/**
 * Resolves the effective appearance for a scope by merging the theme
 * hierarchy: platform, then game, then server. Each level only needs to
 * set what it changes.
 *
 * What changed with the folder restructure: a level's theme is now found
 * through ThemeAssignment (which theme is used where) rather than by
 * filtering Theme rows that carried their own scope, and a theme
 * contributes its whole bundle — tokens, font, widget style defaults, site
 * chrome — not just tokens. Everything is read from the cached `payload`,
 * so resolving a scope is one query per level and never touches the disk.
 */
class ThemeResolver
{
    /** @return array<string, string> */
    public function resolve(?Game $game = null, ?Server $server = null): array
    {
        $tokens = [];

        foreach ($this->cascade($game, $server) as $theme) {
            $tokens = array_merge($tokens, $theme->payload['tokens'] ?? []);
        }

        return $tokens;
    }

    /**
     * Platform first, then any game override, then any server override —
     * ordered so a later entry wins. A level with no assignment simply
     * contributes nothing, which is what makes partial overrides work.
     *
     * @return list<Theme>
     */
    public function cascade(?Game $game = null, ?Server $server = null): array
    {
        $themes = [];

        if ($platform = $this->themeFor(ThemeAssignment::LEVEL_PLATFORM)) {
            $themes[] = $platform;
        }
        if ($game && $t = $this->themeFor(ThemeAssignment::LEVEL_GAME, gameId: $game->id)) {
            $themes[] = $t;
        }
        if ($server && $t = $this->themeFor(ThemeAssignment::LEVEL_SERVER, serverId: $server->id)) {
            $themes[] = $t;
        }

        return $themes;
    }

    /** The most specific theme in scope, or null when nothing is assigned anywhere. */
    public function effectiveTheme(?Game $game = null, ?Server $server = null): ?Theme
    {
        $cascade = $this->cascade($game, $server);

        return end($cascade) ?: null;
    }

    protected function themeFor(string $level, ?int $gameId = null, ?int $serverId = null): ?Theme
    {
        return ThemeAssignment::query()
            ->where('level', $level)
            ->when($gameId, fn ($q) => $q->where('game_id', $gameId))
            ->when($serverId, fn ($q) => $q->where('server_id', $serverId))
            ->with('theme')
            ->first()?->theme;
    }

    /**
     * Font is scoped by *page*, not by the game/server cascade above — the
     * only scoping that covers Home and the Games listing, neither of
     * which has a Game or Server to hang a theme off. Two levels: this
     * page's own override, else whatever the effective theme provides. A
     * null font_asset_id on the layout *is* "use the theme's font".
     *
     * The page-level override still points at an Asset in the shared
     * library rather than at a theme's own font folder: it's deliberately
     * a per-page exception to the theme, so making it live inside the
     * theme it's overriding would be backwards.
     *
     * @return array{family: string, url: string}|null
     */
    public function resolveFont(?PageLayout $layout, ?Theme $theme = null): ?array
    {
        if ($layout?->font_asset_id && $asset = Asset::find($layout->font_asset_id)) {
            return ['family' => "gh-font-{$asset->id}", 'url' => $asset->url];
        }

        return $theme?->payload['font'] ?? null;
    }

    /**
     * Global widget style defaults, now carried by the effective theme
     * rather than by SiteOption. Still the one app-wide layer beneath each
     * widget's own override, which is resolved client-side.
     */
    public function widgetStyleDefaults(?Theme $theme = null): array
    {
        return $theme?->payload['widgetStyle'] ?? [];
    }

    /**
     * Header/favicon — the shell around the pages. Also the theme's now,
     * with one exception: SpaController still needs a favicon URL before
     * any request scoping exists, so platformFavicon() below is what it
     * calls.
     */
    public function siteChrome(?Theme $theme = null): array
    {
        $site = $theme?->payload['site'] ?? [];

        return [
            'favicon_url' => $theme?->payload['favicon_url'] ?? null,
            // Empty object rather than null when unset: the client spreads
            // this into a style, and a shape that changes type when empty
            // is a trap for whatever consumes it.
            'background' => (object) ($site['background'] ?? []),
            'nav_enabled' => (bool) ($site['nav_enabled'] ?? true),
            'nav_position' => $site['nav_position'] ?? 'top',
            'nav_mirror' => $site['nav_mirror'] ?? 'sidebar_follows_header',
            // No (object) cast here, unlike `background`: a region always
            // has keys, so it can never serialize as [] — and casting made
            // it awkward to read on the PHP side for no gain.
            'header' => $site['header'] ?? ThemeBundle::HEADER_DEFAULTS,
            'sidebar' => $site['sidebar'] ?? ThemeBundle::SIDEBAR_DEFAULTS,
        ];
    }

    /**
     * The favicon for the SPA shell. Always the platform theme's: the
     * shell is served before any route has been matched, so there is no
     * game or server in scope to narrow it with.
     */
    public function platformFavicon(): ?string
    {
        return $this->themeFor(ThemeAssignment::LEVEL_PLATFORM)?->payload['favicon_url'] ?? null;
    }

    /**
     * The site's own identity — name, tagline, logo. Deliberately NOT part
     * of a theme: a theme exported and handed to another community must
     * not arrive carrying someone else's logo. The theme only decides
     * whether each surface *shows* this (see the region blocks'
     * show_branding), never what it says.
     *
     * Served alongside the theme because the shell fetches that on every
     * page anyway and the branding block renders in it — one request
     * rather than two for something every page needs.
     */
    public function branding(): array
    {
        $logoId = SiteOption::value('logo_asset_id');

        return [
            'name' => (string) SiteOption::value('site_name', config('app.name')),
            'tagline' => SiteOption::value('site_tagline') ?: null,
            'logo_url' => $logoId ? Asset::find($logoId)?->url : null,
        ];
    }
}
