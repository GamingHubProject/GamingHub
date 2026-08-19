<?php

use App\Http\Controllers\PageTreeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Public frontend SPA — production build only (resources/spa-dist doesn't
// exist in dev, where the SPA runs from its own Vite dev server on a
// separate port instead). Serves everything explicitly rather than
// leaning on the webserver's own static-file detection: a real asset path
// (e.g. /ui/assets/index-abc123.js) returns that file directly; anything
// else (a client-side route like /ui/games/ark, or the bare /ui) returns
// the SPA's index.html and lets its React Router take over. Must come
// before the Web Tree catch-all below.
Route::get('/ui/{path?}', function (?string $path = null) {
    $root = resource_path('spa-dist');
    abort_unless(is_dir($root), 404);

    if ($path !== null) {
        $file = realpath($root.'/'.$path);
        if ($file !== false && str_starts_with($file, $root) && is_file($file)) {
            return response()->file($file);
        }
    }

    return response()->file($root.'/index.html');
})->where('path', '.*');

// Web Tree — must stay last: it's a catch-all for any remaining path
// ("games/ark/ragnarok"), so every more specific route above needs to
// match first.
Route::get('/{path}', [PageTreeController::class, 'show'])
    ->where('path', '.*')
    ->name('pages.show');
