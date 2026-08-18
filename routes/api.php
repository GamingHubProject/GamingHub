<?php

use App\Http\Controllers\Api\DashboardPageController;
use App\Http\Controllers\Api\DashboardWidgetController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\ThemeController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public, unauthenticated — games/servers/theme/pages are all meant to be
| readable by an anonymous visitor to the public SPA (matching the Web
| Tree's own published-content default), same as the existing Blade
| /{path} catch-all in routes/web.php doesn't require login either.
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::get('/games', [GameController::class, 'index']);
    Route::get('/games/{slug}', [GameController::class, 'show']);
    Route::get('/games/{slug}/servers', [GameController::class, 'servers']);
    Route::get('/servers/{server}', [ServerController::class, 'show']);
    Route::get('/theme', [ThemeController::class, 'show']);
    // Web Tree paths can contain slashes ("games/ark/ragnarok") — {path}
    // has to opt into matching them explicitly, same as the Blade route.
    Route::get('/pages/{path}', [PageController::class, 'show'])->where('path', '.*');

    /*
    |----------------------------------------------------------------------
    | Authenticated — the player's own session (Sanctum SPA cookie auth)
    | and their own dashboard only. auth:sanctum here resolves via the
    | 'web' guard for stateful (cookie) requests, the same guard Filament
    | and Breeze already use — see config/sanctum.php's 'guard'.
    |----------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [UserController::class, 'show']);

        Route::get('/dashboard/pages', [DashboardPageController::class, 'index']);
        Route::post('/dashboard/pages', [DashboardPageController::class, 'store']);

        Route::post('/dashboard/widgets', [DashboardWidgetController::class, 'store']);
        Route::patch('/dashboard/widgets/{widget}', [DashboardWidgetController::class, 'update']);
    });
});
