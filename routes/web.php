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

// Web Tree — must stay last: it's a catch-all for any remaining path
// ("games/ark/ragnarok"), so every more specific route above needs to
// match first.
Route::get('/{path}', [PageTreeController::class, 'show'])
    ->where('path', '.*')
    ->name('pages.show');
