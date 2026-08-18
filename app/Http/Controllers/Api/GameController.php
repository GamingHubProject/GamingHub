<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\GameResource;
use GamingHub\Core\Models\Game;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GameController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $games = Game::query()
            ->where('status', 'enabled')
            ->orderBy('name')
            ->get();

        return GameResource::collection($games);
    }

    public function show(string $slug): GameResource
    {
        $game = Game::query()
            ->where('slug', $slug)
            ->where('status', 'enabled')
            ->firstOrFail();

        return new GameResource($game);
    }
}
