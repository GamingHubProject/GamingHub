<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

// Named "dashboard" (rather than folded into the catch-all below) because
// Breeze's own navigation.blade.php — still used by /profile — resolves
// route('dashboard') to build its nav links. Serves the same SPA shell as
// everything else; the SPA's own AuthProvider renders the logged-out state
// client-side, but auth+verified still gate it server-side so a logged-out
// visit redirects to /login instead of flashing the shell first.
Route::get('/dashboard', [SpaController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Everything else — including bare "/" and arbitrary Web Tree paths like
// "/games/ark/ragnarok" — is the public React SPA. Must stay last: it's a
// catch-all, so every more specific route above needs to match first. See
// SpaController for how it decides between a real asset file and the SPA's
// index.html.
//
// Excludes "admin" as a first path segment: without this, a bogus/
// unregistered Filament sub-route (no route in the app matches it) would
// fall through and get a 200 SPA shell instead of a 404 — Filament owns
// that whole namespace, and nothing there should ever resolve to the SPA.
Route::get('/{path?}', [SpaController::class, 'show'])->where('path', '^(?!admin(?:/|$)).*$');
