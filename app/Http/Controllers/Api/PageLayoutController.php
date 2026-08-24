<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PageLayoutResource;
use App\Models\PageLayout;
use GamingHub\Core\Models\Game;
use GamingHub\Core\Models\Server;
use Illuminate\Http\JsonResponse;

/**
 * Public — same visibility as the subject page itself. One thin,
 * route-model-bound method per subject type (mirroring how this worked
 * for Server alone before the page_layouts generalization) rather than a
 * single generic "resolve any subject by string type" endpoint: each
 * already has its real subject in hand via route binding (a Server, a
 * Game, or nothing for Home/the games list), so there's nothing generic
 * left to do beyond the shared firstOrCreate below. Widget writes
 * (PageLayoutWidgetController) don't need any of this — a widget row
 * already points at its layout, so those stay subject-agnostic.
 */
class PageLayoutController extends Controller
{
    /**
     * Seeded onto a singleton page's layout the very first time it's ever
     * requested (see resolve()) — so a fresh install's Home/Games pages
     * still show the same games grid they always did, just as a real,
     * admin-editable widget from day one instead of hardcoded markup. Only
     * fires on genuine first creation, never re-applied to an existing
     * (possibly since-emptied) layout.
     */
    private const DEFAULT_WIDGETS = [
        'home' => [
            ['widget_type' => 'game-card', 'config' => ['mode' => 'all', 'game_id' => null, 'game_slug' => null], 'width' => 12, 'height' => 4],
        ],
        'games-list' => [
            ['widget_type' => 'game-card', 'config' => ['mode' => 'all', 'game_id' => null, 'game_slug' => null], 'width' => 12, 'height' => 4],
        ],
    ];

    public function showForServer(Server $server): JsonResponse
    {
        return $this->respond($this->resolve('server', $server->id));
    }

    public function showForGame(string $slug): JsonResponse
    {
        $game = Game::query()->where('slug', $slug)->where('status', 'enabled')->firstOrFail();

        return $this->respond($this->resolve('game', $game->id));
    }

    public function showForHome(): JsonResponse
    {
        return $this->respond($this->resolve('home', PageLayout::SINGLETON_SUBJECT_ID));
    }

    public function showForGamesList(): JsonResponse
    {
        return $this->respond($this->resolve('games-list', PageLayout::SINGLETON_SUBJECT_ID));
    }

    private function resolve(string $subjectType, int $subjectId): PageLayout
    {
        // The layout row is created lazily on first request rather than
        // upfront, same as before the generalization — every existing
        // server (and now every game, and the Home/games-list singletons)
        // already "has" one without a backfill.
        $layout = PageLayout::firstOrCreate([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);

        if ($layout->wasRecentlyCreated) {
            foreach (self::DEFAULT_WIDGETS[$subjectType] ?? [] as $widget) {
                $layout->widgets()->create($widget + ['position_x' => 0, 'position_y' => 0]);
            }
        }

        $layout->loadMissing('widgets');

        return $layout;
    }

    private function respond(PageLayout $layout): JsonResponse
    {
        // Not just `new PageLayoutResource($layout)` — see the same
        // reasoning this class had before the generalization: firstOrCreate
        // makes wasRecentlyCreated true on a layout's very first request,
        // which JsonResource's default toResponse() would otherwise report
        // as 201 for what's always a GET.
        return (new PageLayoutResource($layout))->response()->setStatusCode(200);
    }
}
