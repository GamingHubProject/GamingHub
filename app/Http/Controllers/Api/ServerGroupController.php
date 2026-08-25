<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ServerGroupResource;
use App\Models\ServerGroup;
use GamingHub\Core\Models\Game;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public — same visibility as Server/Game (ServerController, GameController)
 * whose data this is just grouping; nothing sensitive to gate here.
 */
class ServerGroupController extends Controller
{
    public function show(int $group): ServerGroupResource
    {
        return new ServerGroupResource($this->withCounts(ServerGroup::query())->findOrFail($group));
    }

    public function forGame(string $slug): AnonymousResourceCollection
    {
        // 404 for a disabled/unknown game rather than a silent empty list
        // — matches GameController::servers's behavior for the sibling
        // /games/{slug}/servers endpoint exactly.
        $game = Game::query()->where('slug', $slug)->where('status', 'enabled')->firstOrFail();

        $groups = $this->withCounts(ServerGroup::query())
            ->where('game_id', $game->id)
            ->orderBy('name')
            ->get();

        return ServerGroupResource::collection($groups);
    }

    private function withCounts(Builder $query): Builder
    {
        // "Running" mirrors the frontend's StatusBadge green bucket
        // (running/online) — a simple health-at-a-glance fraction rather
        // than a full per-status breakdown, matching server-card's own
        // "minimal by default" spirit.
        return $query->with('game')->withCount([
            'servers',
            'servers as running_count' => fn ($q) => $q->whereIn('status', ['running', 'online']),
        ]);
    }
}
