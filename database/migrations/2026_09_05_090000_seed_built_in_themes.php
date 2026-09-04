<?php

use App\Experience\BuiltInThemes;
use App\Experience\ThemeStorage;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfills the built-in themes onto sites that already exist — a fresh
 * install gets them from the seeder, but an upgrade never runs that.
 *
 * Idempotent by slug (see BuiltInThemes::seed), so this is safe to re-run
 * and won't overwrite a theme an admin has since edited. It also won't
 * change what an existing site currently looks like: the platform
 * assignment is only set when nothing is assigned at all, so an install
 * that already has its own theme in use keeps it, and simply gains three
 * more to choose from.
 */
return new class extends Migration
{
    public function up(): void
    {
        BuiltInThemes::seed(app(ThemeStorage::class));
    }

    public function down(): void
    {
        // Deliberately not removing them: by the time this is rolled back
        // an admin may have edited or assigned one, and deleting a theme
        // in use would take the site's styling with it.
    }
};
