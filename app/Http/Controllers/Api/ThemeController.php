<?php

namespace App\Http\Controllers\Api;

use App\Experience\ThemeResolver;
use App\Http\Controllers\Controller;
use App\Models\PageLayout;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ThemeController extends Controller
{
    public function show(Request $request, ThemeResolver $resolver): JsonResponse
    {
        $game = $request->filled('game_id')
            ? Game::query()->findOrFail($request->integer('game_id'))
            : null;

        $server = $request->filled('server_id')
            ? Server::query()->findOrFail($request->integer('server_id'))
            : null;

        // subject_type/subject_id identify the *page* (page_layouts'
        // scoping) for font resolution — a separate axis from game_id/
        // server_id above, which only drive the color cascade. Home and
        // the Games listing have neither a game nor a server to key off,
        // so this can't just be derived from the two params already here.
        $layout = $request->filled('subject_type')
            ? PageLayout::query()
                ->where('subject_type', $request->string('subject_type'))
                ->where('subject_id', $request->integer('subject_id', PageLayout::SINGLETON_SUBJECT_ID))
                ->first()
            : null;

        // One theme now supplies tokens, font, widget style defaults and
        // site chrome together, so they're all resolved from the same
        // scope rather than each having its own lookup. The response shape
        // is unchanged — the SPA doesn't need to know where any of it
        // came from.
        $theme = $resolver->effectiveTheme($game, $server);

        return response()->json([
            'tokens' => $resolver->resolve($game, $server),
            'font' => $resolver->resolveFont($layout, $theme),
            'widgetStyle' => $resolver->widgetStyleDefaults($theme),
            'site' => $resolver->siteChrome($theme),
        ]);
    }
}
