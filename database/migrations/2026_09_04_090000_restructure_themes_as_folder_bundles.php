<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Themes stop being scoped rows carrying loose token JSON and become named,
 * portable bundles stored as folders in the Asset Library (see
 * App\Experience\ThemeStorage). Two separate changes, both here because
 * neither is safe without the other:
 *
 * 1. `themes` is demoted from source-of-truth to an index over
 *    /themes/{slug}/ — it keeps a parsed copy of theme.json in `payload`
 *    plus a `checksum` so a drifted folder can be detected and re-synced.
 *    Reads (every page load hits /api/v1/theme) stay one cheap DB query
 *    rather than a disk read + JSON parse.
 *
 * 2. Scope moves out of the theme and into `theme_assignments`. A theme
 *    that embeds which game it was installed against can't be exported and
 *    handed to another site — the whole point of the phases that follow —
 *    so "what a theme looks like" and "where this site uses it" become two
 *    different records. This also makes one theme assignable to several
 *    games at once, which the old shape couldn't express at all.
 *
 * The data migration itself lives in ThemeStorage::migrateLegacyThemes(),
 * called from up() — it has to create folders and copy asset files, which
 * is application logic, not schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->constrained()->cascadeOnDelete();
            $table->string('level'); // platform | game | server
            $table->foreignId('game_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One theme per scope. Partial-unique would be tighter, but
            // it isn't portable across the SQLite used in tests and the
            // Postgres used in production — ThemeAssignment::assign()
            // enforces the same thing in one place instead.
            $table->index(['level', 'game_id', 'server_id']);
        });

        Schema::table('themes', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            // Nullable rather than constrained: a theme's folder can be
            // deleted out from under it in the Asset Library, and losing
            // the whole theme row to a cascade would be a worse outcome
            // than a row whose folder is temporarily missing (the sync
            // command reports and repairs that).
            $table->unsignedBigInteger('folder_id')->nullable()->after('slug');
            $table->json('payload')->nullable()->after('tokens');
            $table->string('checksum')->nullable()->after('payload');
            $table->timestamp('synced_at')->nullable()->after('checksum');
            $table->boolean('is_builtin')->default(false)->after('synced_at');
        });

        // Folder creation + file copying, then the scope split.
        app(\App\Experience\ThemeStorage::class)->migrateLegacyThemes();

        Schema::table('themes', function (Blueprint $table) {
            // `tokens` is folded into `payload` by the migration above;
            // level/game_id/server_id are now theme_assignments rows.
            $table->dropConstrainedForeignId('game_id');
            $table->dropConstrainedForeignId('server_id');
            $table->dropColumn(['level', 'tokens', 'is_default']);
        });

        Schema::table('themes', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->string('level')->default('platform');
            $table->foreignId('game_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('tokens')->nullable();
            $table->boolean('is_default')->default(false);
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'folder_id', 'payload', 'checksum', 'synced_at', 'is_builtin']);
        });

        Schema::dropIfExists('theme_assignments');
    }
};
