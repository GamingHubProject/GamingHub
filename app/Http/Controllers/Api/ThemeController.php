<?php

namespace App\Http\Controllers\Api;

use App\Experience\ThemeResolver;
use App\Http\Controllers\Controller;
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

        return response()->json($resolver->resolve($game, $server));
    }
}
