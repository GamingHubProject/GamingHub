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
 * Game, or nothing for Home), so there's nothing generic left to do
 * beyond the shared firstOrCreate below. Widget writes (PageLayoutWidget
 * Controller) don't need any of this — a widget row already points at its
 * layout, so those stay subject-agnostic.
 */
class PageLayoutController extends Controller
{
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
        return $this->respond($this->resolve('home', PageLayout::HOME_SUBJECT_ID));
    }

    private function resolve(string $subjectType, int $subjectId): PageLayout
    {
        // The layout row is created lazily on first request rather than
        // upfront, same as before the generalization — every existing
        // server (and now every game, and the Home singleton) already
        // "has" one without a backfill.
        $layout = PageLayout::with('widgets')->firstOrCreate([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);
        // with('widgets') only applies on the "found" path — see
        // PageLayoutController's pre-generalization docblock for why this
        // loadMissing is needed on firstOrCreate's "created" path.
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
