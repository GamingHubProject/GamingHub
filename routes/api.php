<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetFolderController;
use App\Http\Controllers\Api\AssetTagController;
use App\Http\Controllers\Api\DashboardPageController;
use App\Http\Controllers\Api\DashboardWidgetController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GroupWidgetTemplateController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PageLayoutController;
use App\Http\Controllers\Api\PageLayoutWidgetController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\ServerGroupController;
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
    // For server-group-card's config picker (cascading game -> group,
    // mirroring server-card's game -> server picker) and the widget's own
    // render.
    Route::get('/games/{slug}/server-groups', [ServerGroupController::class, 'forGame']);
    Route::get('/server-groups/{group}', [ServerGroupController::class, 'show']);
    Route::get('/servers/{server}', [ServerController::class, 'show']);
    // Shared/admin-owned, not a player's own — every visitor sees the same
    // layout (see App\Models\PageLayout's docblock). Public like the
    // subject page itself; only the widgets sub-resource's writes are
    // gated. One thin read endpoint per subject type (Server/Game/Home/
    // the games list), each already route-bound to its real subject —
    // see PageLayoutController's docblock for why this isn't one generic
    // "resolve by string type" route instead. /games-list/layout (not
    // /games/layout) to stay unambiguous next to /games/{slug} above.
    Route::get('/servers/{server}/layout', [PageLayoutController::class, 'showForServer']);
    Route::get('/games/{slug}/layout', [PageLayoutController::class, 'showForGame']);
    Route::get('/home/layout', [PageLayoutController::class, 'showForHome']);
    Route::get('/games-list/layout', [PageLayoutController::class, 'showForGamesList']);
    Route::get('/theme', [ThemeController::class, 'show']);
    // Browsing the library needs no more privilege than browsing
    // games/servers/theme — only uploading/deleting are gated (below).
    // Visibility of individual assets/folders is still enforced per-row
    // inside the controllers (folder scoping needs the requesting user,
    // which is why these aren't behind auth:sanctum — an anonymous
    // visitor is a valid "user" of null for that scoping, same as today).
    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/asset-folders', [AssetFolderController::class, 'index']);
    Route::get('/asset-tags', [AssetTagController::class, 'index']);
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

        // Admin-gated inline in the controller (hasRole('Admin')), not by
        // route middleware — no 'role:' middleware alias is registered in
        // this app; every other per-request authorization check in this
        // codebase (e.g. DashboardWidgetController's ownership check) is
        // likewise done inline rather than via middleware.
        // Subject-agnostic — see PageLayoutWidgetController's docblock.
        // The frontend always fetches the layout first (one of the
        // subject-specific GET routes above), so it already has the real
        // layout id by the time it adds a widget.
        Route::post('/page-layouts/{layout}/widgets', [PageLayoutWidgetController::class, 'store']);
        Route::patch('/page-layouts/{layout}', [PageLayoutController::class, 'update']);
        Route::patch('/page-layout-widgets/{widget}', [PageLayoutWidgetController::class, 'update']);
        Route::delete('/page-layout-widgets/{widget}', [PageLayoutWidgetController::class, 'destroy']);

        // Admin-only, editor-only — see GroupWidgetTemplateController's
        // docblock. No public browse endpoint; a template is never
        // rendered to a visitor, only used while editing a layout.
        Route::get('/group-widget-templates', [GroupWidgetTemplateController::class, 'index']);
        Route::post('/group-widget-templates', [GroupWidgetTemplateController::class, 'store']);
        Route::delete('/group-widget-templates/{template}', [GroupWidgetTemplateController::class, 'destroy']);
        Route::post('/page-layouts/{layout}/group-widgets/from-template/{template}', [GroupWidgetTemplateController::class, 'place']);

        Route::post('/assets', [AssetController::class, 'store']);
        Route::patch('/assets/{asset}', [AssetController::class, 'update']);
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);

        // Static path, must come before /asset-folders/{folder}-style
        // dynamic routes if any existed — kept alongside store() for the
        // same "Admin-only write surface" grouping even though it's
        // technically idempotent-after-first-call (see the controller
        // method's docblock).
        Route::get('/asset-folders/fonts', [AssetFolderController::class, 'fonts']);
        Route::post('/asset-folders', [AssetFolderController::class, 'store']);
        Route::patch('/asset-folders/{folder}', [AssetFolderController::class, 'update']);
        Route::delete('/asset-folders/{folder}', [AssetFolderController::class, 'destroy']);

        Route::post('/asset-tags', [AssetTagController::class, 'store']);
        Route::delete('/asset-tags/{tag}', [AssetTagController::class, 'destroy']);
    });
});
