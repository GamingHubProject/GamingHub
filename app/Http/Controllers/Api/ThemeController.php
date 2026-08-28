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

        $font = $resolver->resolveFont($layout);

        return response()->json([
            'tokens' => $resolver->resolve($game, $server),
            'font' => $font ? [
                // Synthetic, stable — never shown to an admin, just needs
                // to be a valid, collision-free CSS identifier. No new
                // "font display name" field on Asset for this.
                'family' => "gh-font-{$font->id}",
                'url' => $font->url,
            ] : null,
            // Global widget style defaults — additive, not another
            // breaking shape change like font's rollout. No params needed
            // (see ThemeResolver::widgetStyleDefaults's docblock).
            'widgetStyle' => $resolver->widgetStyleDefaults(),
        ]);
    }
}
