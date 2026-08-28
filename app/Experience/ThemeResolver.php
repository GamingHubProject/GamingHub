<?php

namespace App\Experience;

use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use App\Models\Asset;
use App\Models\PageLayout;
use App\Models\SiteOption;
use App\Models\Theme;

/**
 * Resolves the effective design tokens for a given scope by merging the
 * theme hierarchy: platform defaults, then game overrides, then server
 * overrides. Each level only needs to set the tokens it wants to change.
 */
class ThemeResolver
{
    public function resolve(?Game $game = null, ?Server $server = null): array
    {
        $tokens = [];

        $tokens = array_merge($tokens, $this->tokensFor(Theme::LEVEL_PLATFORM));

        if ($game) {
            $tokens = array_merge($tokens, $this->tokensFor(Theme::LEVEL_GAME, gameId: $game->id));
        }

        if ($server) {
            $tokens = array_merge($tokens, $this->tokensFor(Theme::LEVEL_SERVER, serverId: $server->id));
        }

        return $tokens;
    }

    protected function tokensFor(string $level, ?int $gameId = null, ?int $serverId = null): array
    {
        $theme = Theme::query()
            ->where('level', $level)
            ->when($gameId, fn ($query) => $query->where('game_id', $gameId))
            ->when($serverId, fn ($query) => $query->where('server_id', $serverId))
            ->orderByDesc('is_default')
            ->first();

        return $theme?->tokens ?? [];
    }

    /**
     * A separate, much simpler axis from resolve()'s platform/game/server
     * color cascade: font is scoped by *page* (page_layouts' subject),
     * which is the only scoping that covers Home and the Games listing —
     * neither has a Game/Server to hang a Theme row off of. Just two
     * levels: this page's own override, else the platform-wide default —
     * a null font_asset_id on the layout *is* "sync to global", not a
     * separate flag.
     */
    public function resolveFont(?PageLayout $layout): ?Asset
    {
        $assetId = $layout?->font_asset_id ?? SiteOption::value('font_asset_id');

        return $assetId ? Asset::find($assetId) : null;
    }

    /**
     * Purely global — no page/game/server cascade like resolve()'s color
     * tokens, and no page-level tier like resolveFont() either (confirmed:
     * border/text/background are naturally per-widget-instance, not a
     * whole-page choice). A widget's own config carries its override, if
     * any; this is just the one app-wide fallback layer beneath that,
     * resolved client-side (see the frontend's resolveWidgetStyle) since
     * there's nothing server-scoped left to compute once this one value is
     * fetched.
     */
    public function widgetStyleDefaults(): array
    {
        return SiteOption::value('widget_style_defaults', []) ?? [];
    }
}
